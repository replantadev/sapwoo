<?php
/**
 * Sincronización de Productos SAP → WooCommerce
 * 
 * Importa Items de SAP como productos de WooCommerce.
 * Soporta importación por lotes (batch) para operaciones asíncronas.
 * Crea productos nuevos si no existen (match por SKU) y actualiza los existentes.
 *
 * @package SAPWC
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAPWC_Product_Sync
{
    /**
     * Campos a obtener de SAP para la importación
     */
    private static function get_select_fields()
    {
        $fields = [
            'ItemCode',
            'ItemName',
            'ForeignName',
            'ItemsGroupCode',
            'BarCode',
            'SalesUnit',
            'ItemPrices',
            'ItemWarehouseInfoCollection',
            'Valid',           // si el artículo está activo
            'FrozenFor',       // si está congelado
            'QuantityOnStock', // stock global
        ];

        return implode(',', $fields);
    }

    /**
     * Obtiene un lote de Items desde SAP
     *
     * @param int $skip  Offset para paginación
     * @param int $top   Tamaño de lote
     * @return array
     */
    public static function fetch_from_sap($skip = 0, $top = 20)
    {
        $conn = sapwc_get_active_connection();
        if (!$conn) {
            return ['error' => __('No hay conexión activa con SAP.', 'sapwoo')];
        }

        $client = new SAPWC_API_Client($conn['url']);
        $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

        if (!$login['success']) {
            return ['error' => __('Error de login SAP: ', 'sapwoo') . $login['message']];
        }

        $select = self::get_select_fields();

        // Intentar primero con filtro completo
        $filter = "Valid eq 'tYES' and ItemType eq 'itItems'";
        $filter_encoded = urlencode($filter);
        $query = "/Items?\$filter={$filter_encoded}&\$select={$select}&\$orderby=ItemCode&\$top={$top}&\$skip={$skip}";
        $response = $client->get($query);

        // Si falla, intentar solo con ItemType
        if (!isset($response['value'])) {
            $filter = "ItemType eq 'itItems'";
            $filter_encoded = urlencode($filter);
            $query = "/Items?\$filter={$filter_encoded}&\$select={$select}&\$orderby=ItemCode&\$top={$top}&\$skip={$skip}";
            $response = $client->get($query);
        }

        // Si sigue fallando, intentar sin filtro
        if (!isset($response['value'])) {
            $query = "/Items?\$select={$select}&\$orderby=ItemCode&\$top={$top}&\$skip={$skip}";
            $response = $client->get($query);
        }

        $client->logout();

        if (!isset($response['value'])) {
            $error_msg = __('No se pudieron obtener los artículos de SAP.', 'sapwoo');
            if (isset($response['error']) && isset($response['error']['message'])) {
                $error_msg .= ' ' . $response['error']['message']['value'];
            }
            return ['error' => $error_msg];
        }

        return [
            'items'         => $response['value'],
            'has_more'      => count($response['value']) === $top,
            'total_fetched' => count($response['value']),
        ];
    }

    /**
     * Obtiene el conteo total estimado de productos en SAP
     *
     * @return int
     */
    public static function get_total_count()
    {
        $conn = sapwc_get_active_connection();
        if (!$conn) return 0;

        $client = new SAPWC_API_Client($conn['url']);
        $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
        if (!$login['success']) return 0;

        $response = $client->get("/Items/\$count");
        $client->logout();

        return is_numeric($response) ? (int) $response : 0;
    }

    /**
     * Importa/actualiza un producto individual desde datos SAP
     *
     * @param array $item_data  Datos del item de SAP
     * @param array $options    Opciones de importación
     * @return array
     */
    public static function import_product($item_data, $options = [])
    {
        $defaults = [
            'update_existing'  => true,
            'import_prices'    => true,
            'import_stock'     => true,
            'assign_category'  => true,
            'default_status'   => 'draft', // draft o publish
        ];
        $opts = wp_parse_args($options, $defaults);

        $sku  = trim($item_data['ItemCode'] ?? '');
        $name = trim($item_data['ItemName'] ?? '');

        if (empty($sku)) {
            return ['success' => false, 'message' => __('ItemCode vacío.', 'sapwoo'), 'sku' => $sku];
        }

        if (empty($name)) {
            $name = $sku; // Fallback al código si no hay nombre
        }

        // Buscar producto existente por SKU
        $product_id = wc_get_product_id_by_sku($sku);
        $is_new = empty($product_id);

        if (!$is_new && !$opts['update_existing']) {
            return [
                'success' => true,
                'action'  => 'skipped',
                'sku'     => $sku,
                'message' => __('Producto ya existe, actualización desactivada.', 'sapwoo'),
            ];
        }

        // Crear o cargar el producto
        if ($is_new) {
            $product = new WC_Product_Simple();
            $product->set_name($name);
            $product->set_sku($sku);
            $product->set_status($opts['default_status']);
            $product->set_catalog_visibility('visible');
        } else {
            $product = wc_get_product($product_id);
            if (!$product) {
                return ['success' => false, 'message' => __('Error cargando producto existente.', 'sapwoo'), 'sku' => $sku];
            }
            // Actualizar nombre si cambió
            if ($product->get_name() !== $name) {
                $product->set_name($name);
            }
        }

        // Nombre extranjero como descripción corta si existe
        $foreign_name = trim($item_data['ForeignName'] ?? '');
        if (!empty($foreign_name) && $is_new) {
            $product->set_short_description($foreign_name);
        }

        // Código de barras como meta
        $barcode = trim($item_data['BarCode'] ?? '');
        if (!empty($barcode)) {
            $product->update_meta_data('_sapwc_barcode', $barcode);
        }

        // Unidad de venta
        $sales_unit = trim($item_data['SalesUnit'] ?? '');
        if (!empty($sales_unit)) {
            $product->update_meta_data('_sapwc_sales_unit', $sales_unit);
        }

        // Precio
        if ($opts['import_prices']) {
            $price = self::extract_price($item_data);
            if ($price !== null) {
                $product->set_regular_price($price);
                $product->set_price($price);
                $product->update_meta_data('_sapwc_price_net', $price);
            }
        }

        // Stock
        if ($opts['import_stock']) {
            $stock = self::extract_stock($item_data);
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock);
            $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
            $product->update_meta_data('_sapwc_stock_total', $stock);
        }

        // Categoría basada en ItemsGroupCode
        if ($opts['assign_category'] && isset($item_data['ItemsGroupCode'])) {
            $group_code = $item_data['ItemsGroupCode'];
            $cat_mapping = SAPWC_Category_Sync::get_mapping();

            if (isset($cat_mapping[$group_code])) {
                $product->set_category_ids([$cat_mapping[$group_code]]);
            }
        }

        // Meta de origen SAP
        $product->update_meta_data('_sapwc_imported', '1');
        $product->update_meta_data('_sapwc_items_group_code', $item_data['ItemsGroupCode'] ?? '');
        $product->update_meta_data('_sapwc_last_import', current_time('mysql'));

        // Almacén (para compatibilidad B2B)
        $almacenes = get_option('sapwc_selected_warehouses', ['01']);
        if (!empty($almacenes) && is_array($almacenes)) {
            $product->update_meta_data('almacen', $almacenes[0]);
        }

        // Guardar
        $saved_id = $product->save();

        if (!$saved_id) {
            return ['success' => false, 'message' => __('Error al guardar el producto.', 'sapwoo'), 'sku' => $sku];
        }

        return [
            'success'    => true,
            'action'     => $is_new ? 'created' : 'updated',
            'product_id' => $saved_id,
            'sku'        => $sku,
            'name'       => $name,
        ];
    }

    /**
     * Extrae el precio del item según la tarifa configurada
     *
     * @param array $item_data
     * @return float|null
     */
    private static function extract_price($item_data)
    {
        $tariff = get_option('sapwc_selected_tariff', '');
        $use_vat = get_option('sapwc_use_price_after_vat', '0') === '1';

        if (empty($tariff) || !isset($item_data['ItemPrices'])) {
            return null;
        }

        // Tarifa por almacén del producto
        $product_warehouse = '';
        $almacenes = get_option('sapwc_selected_warehouses', ['01']);
        if (!empty($almacenes)) {
            $product_warehouse = $almacenes[0];
        }

        $warehouse_tariffs = get_option('sapwc_warehouse_tariff_map', []);
        if (!empty($product_warehouse) && isset($warehouse_tariffs[$product_warehouse])) {
            $tariff = $warehouse_tariffs[$product_warehouse];
        }

        foreach ($item_data['ItemPrices'] as $price) {
            if ((string) $price['PriceList'] === (string) $tariff) {
                if ($use_vat && isset($price['PriceAfterVAT']) && (float) $price['PriceAfterVAT'] > 0) {
                    return round((float) $price['PriceAfterVAT'], 4);
                }
                return (float) $price['Price'];
            }
        }

        return null;
    }

    /**
     * Extrae el stock del item de los almacenes configurados
     *
     * @param array $item_data
     * @return float
     */
    private static function extract_stock($item_data)
    {
        $almacenes = get_option('sapwc_selected_warehouses', ['01']);
        if (!is_array($almacenes)) $almacenes = ['01'];

        $stock_total = 0;

        if (isset($item_data['ItemWarehouseInfoCollection'])) {
            foreach ($item_data['ItemWarehouseInfoCollection'] as $wh) {
                if (in_array($wh['WarehouseCode'] ?? '', $almacenes)) {
                    $stock_total += (float) ($wh['InStock'] ?? 0);
                }
            }
        }

        return $stock_total;
    }

    /**
     * Importa un lote de productos (para operación asíncrona por AJAX)
     *
     * @param int   $skip    Offset
     * @param int   $batch   Tamaño de lote
     * @param array $options Opciones de importación
     * @return array
     */
    public static function import_batch($skip = 0, $batch = 20, $options = [])
    {
        $fetch = self::fetch_from_sap($skip, $batch);

        if (isset($fetch['error'])) {
            return ['success' => false, 'message' => $fetch['error']];
        }

        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
            'details' => [],
        ];

        foreach ($fetch['items'] as $item) {
            $result = self::import_product($item, $options);

            if ($result['success']) {
                $results[$result['action']]++;
            } else {
                $results['errors']++;
            }

            $results['details'][] = $result;
        }

        $results['has_more']   = $fetch['has_more'];
        $results['next_skip']  = $skip + $batch;
        $results['success']    = true;
        $results['batch_size'] = count($fetch['items']);
        $results['message']    = sprintf(
            __('Lote procesado: %d creados, %d actualizados, %d omitidos, %d errores', 'sapwoo'),
            $results['created'],
            $results['updated'],
            $results['skipped'],
            $results['errors']
        );

        return $results;
    }

    /**
     * Importa TODOS los productos (completo, sin batches)
     *
     * @param array $options
     * @return array
     */
    public static function import_all($options = [])
    {
        if (get_transient('sapwc_product_sync_lock')) {
            return ['success' => false, 'locked' => true, 'message' => __('Importación ya en curso.', 'sapwoo')];
        }

        set_transient('sapwc_product_sync_lock', 1, 30 * MINUTE_IN_SECONDS);

        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $skip = 0;
        $batch = 20;

        try {
            do {
                $result = self::import_batch($skip, $batch, $options);

                if (!$result['success']) {
                    delete_transient('sapwc_product_sync_lock');
                    return $result;
                }

                $totals['created'] += $result['created'];
                $totals['updated'] += $result['updated'];
                $totals['skipped'] += $result['skipped'];
                $totals['errors']  += $result['errors'];

                $skip = $result['next_skip'];

                // Prevenir memory exhaustion
                if (function_exists('wp_cache_flush')) {
                    wp_cache_flush();
                }
            } while ($result['has_more']);

            update_option('sapwc_products_last_sync', current_time('mysql'));

            $msg = sprintf(
                __('Productos sincronizados: %d creados, %d actualizados, %d omitidos, %d errores', 'sapwoo'),
                $totals['created'],
                $totals['updated'],
                $totals['skipped'],
                $totals['errors']
            );

            SAPWC_Logger::log(null, 'product_sync', 'success', $msg);

            return array_merge($totals, [
                'success'   => true,
                'message'   => $msg,
                'last_sync' => current_time('mysql'),
            ]);
        } finally {
            delete_transient('sapwc_product_sync_lock');
        }
    }

    /**
     * Obtiene estadísticas de productos importados
     *
     * @return array
     */
    public static function get_stats()
    {
        global $wpdb;

        $imported = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_sapwc_imported' AND meta_value = '1'"
        );

        $total_woo = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish', 'draft', 'private')"
        );

        return [
            'total_imported' => (int) $imported,
            'total_woo'      => (int) $total_woo,
            'last_sync'      => get_option('sapwc_products_last_sync', __('Nunca', 'sapwoo')),
        ];
    }

    /**
     * Callback del cron automático
     */
    public static function cron_callback()
    {
        if (get_option('sapwc_sync_products_auto', '0') !== '1') {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAPWC] Ejecutando sincronización automática de productos');
        }
        $result = self::import_all(['update_existing' => true, 'default_status' => 'publish']);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAPWC] Resultado productos: ' . ($result['message'] ?? json_encode($result)));
        }
    }

    /**
     * Verifica si un producto de SAP ya existe en WooCommerce
     *
     * @param string $item_code ItemCode/SKU del producto
     * @return bool
     */
    public static function product_exists_in_woo($item_code)
    {
        $product_id = wc_get_product_id_by_sku($item_code);
        return !empty($product_id);
    }

    /**
     * Obtiene la lista de productos de SAP que NO están en WooCommerce
     *
     * @param int $skip  Offset
     * @param int $top   Límite
     * @return array
     */
    public static function get_pending_products($skip = 0, $top = 50)
    {
        $all_sap = self::fetch_from_sap($skip, $top);

        if (isset($all_sap['error'])) {
            return $all_sap;
        }

        $pending = [];
        foreach ($all_sap['items'] as $item) {
            $sku = $item['ItemCode'] ?? '';
            if (!empty($sku) && !self::product_exists_in_woo($sku)) {
                $pending[] = $item;
            }
        }

        return [
            'items'         => $pending,
            'total_fetched' => count($pending),
            'has_more'      => $all_sap['has_more'],
            'next_skip'     => $skip + $top,
        ];
    }

    /**
     * Obtiene el preview de campos de un producto de SAP para mostrar antes de importar
     *
     * @param string $item_code ItemCode del producto
     * @return array
     */
    public static function get_product_preview($item_code)
    {
        $conn = sapwc_get_active_connection();
        if (!$conn) {
            return ['error' => __('No hay conexión activa con SAP.', 'sapwoo')];
        }

        $client = new SAPWC_API_Client($conn['url']);
        $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

        if (!$login['success']) {
            return ['error' => __('Error de login SAP: ', 'sapwoo') . $login['message']];
        }

        $select = self::get_select_fields();
        $item_code_enc = urlencode($item_code);
        $query = "/Items('{$item_code_enc}')?\$select={$select}";
        $response = $client->get($query);
        $client->logout();

        if (empty($response) || isset($response['error'])) {
            return ['error' => __('Producto no encontrado en SAP.', 'sapwoo')];
        }

        // Construir preview de mapeo de campos
        $tariff = get_option('sapwc_selected_tariff', '');
        $almacenes = get_option('sapwc_selected_warehouses', ['01']);
        $use_vat = get_option('sapwc_use_price_after_vat', '0') === '1';

        // Extraer precio
        $price = null;
        $price_list_name = '';
        if (!empty($tariff) && isset($response['ItemPrices'])) {
            foreach ($response['ItemPrices'] as $p) {
                if ((string)$p['PriceList'] === (string)$tariff) {
                    $price_list_name = 'Lista ' . $p['PriceList'];
                    if ($use_vat && isset($p['PriceAfterVAT']) && (float)$p['PriceAfterVAT'] > 0) {
                        $price = round((float)$p['PriceAfterVAT'], 4);
                    } else {
                        $price = (float)$p['Price'];
                    }
                    break;
                }
            }
        }

        // Extraer stock
        $stock = 0;
        $stock_details = [];
        if (isset($response['ItemWarehouseInfoCollection'])) {
            foreach ($response['ItemWarehouseInfoCollection'] as $wh) {
                if (in_array($wh['WarehouseCode'] ?? '', $almacenes)) {
                    $stock += (float)($wh['InStock'] ?? 0);
                    $stock_details[] = $wh['WarehouseCode'] . ': ' . ($wh['InStock'] ?? 0);
                }
            }
        }

        // Categoría
        $cat_mapping = class_exists('SAPWC_Category_Sync') ? SAPWC_Category_Sync::get_mapping() : [];
        $group_code = $response['ItemsGroupCode'] ?? '';
        $cat_name = '';
        if (!empty($group_code) && isset($cat_mapping[$group_code])) {
            $term = get_term($cat_mapping[$group_code], 'product_cat');
            if ($term && !is_wp_error($term)) {
                $cat_name = $term->name;
            }
        }

        return [
            'sap_data' => [
                ['field' => 'ItemCode', 'label' => 'Código SAP', 'value' => $response['ItemCode'] ?? ''],
                ['field' => 'ItemName', 'label' => 'Nombre', 'value' => $response['ItemName'] ?? ''],
                ['field' => 'ForeignName', 'label' => 'Nombre Alternativo', 'value' => $response['ForeignName'] ?? ''],
                ['field' => 'BarCode', 'label' => 'Código de Barras', 'value' => $response['BarCode'] ?? ''],
                ['field' => 'SalesUnit', 'label' => 'Unidad de Venta', 'value' => $response['SalesUnit'] ?? ''],
                ['field' => 'ItemsGroupCode', 'label' => 'Grupo de Artículos', 'value' => $group_code],
                ['field' => 'Price', 'label' => "Precio ({$price_list_name})", 'value' => $price !== null ? number_format($price, 2) : '-'],
                ['field' => 'Stock', 'label' => 'Stock (' . implode(', ', $almacenes) . ')', 'value' => $stock . ($stock_details ? ' (' . implode(', ', $stock_details) . ')' : '')],
            ],
            'woo_mapping' => [
                ['field' => 'sku', 'label' => 'SKU', 'source' => 'ItemCode'],
                ['field' => 'name', 'label' => 'Nombre Producto', 'source' => 'ItemName'],
                ['field' => 'short_description', 'label' => 'Descripción Corta', 'source' => 'ForeignName'],
                ['field' => '_sapwc_barcode', 'label' => 'Meta: Código Barras', 'source' => 'BarCode'],
                ['field' => '_sapwc_sales_unit', 'label' => 'Meta: Unidad Venta', 'source' => 'SalesUnit'],
                ['field' => 'product_cat', 'label' => 'Categoría', 'source' => $cat_name ?: 'ItemsGroupCode → (sin mapear)'],
                ['field' => 'regular_price', 'label' => 'Precio Regular', 'source' => 'Price'],
                ['field' => 'stock_quantity', 'label' => 'Cantidad Stock', 'source' => 'Stock'],
            ],
            'raw' => $response,
        ];
    }

    /**
     * Importar un producto individual por su ItemCode
     *
     * @param string $item_code
     * @param array $options
     * @return array
     */
    public static function import_single($item_code, $options = [])
    {
        $default_options = [
            'update_existing'  => true,
            'import_prices'    => true,
            'import_stock'     => true,
            'assign_category'  => true,
            'default_status'   => 'draft',
        ];
        $options = wp_parse_args($options, $default_options);

        $conn = sapwc_get_active_connection();
        if (!$conn) {
            return ['error' => __('No hay conexión activa con SAP.', 'sapwoo')];
        }

        $client = new SAPWC_API_Client($conn['url']);
        $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

        if (!$login['success']) {
            return ['error' => __('Error de login SAP: ', 'sapwoo') . $login['message']];
        }

        $select = self::get_select_fields();
        $item_code_enc = urlencode($item_code);
        $query = "/Items('{$item_code_enc}')?\$select={$select}";
        $response = $client->get($query);
        $client->logout();

        if (empty($response) || isset($response['error'])) {
            return ['error' => __('Producto no encontrado en SAP.', 'sapwoo')];
        }

        // Procesar el producto
        $result = self::process_product($response, $options);

        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        return ['success' => true, 'message' => $result['message'] ?? __('Producto importado correctamente.', 'sapwoo')];
    }
}
