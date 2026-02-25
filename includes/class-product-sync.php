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

        // Filtrar solo artículos activos y de tipo inventario (no servicios)
        $filter = "Valid eq 'tYES' and ItemType eq 'itItems'";
        $filter_encoded = urlencode($filter);

        $query = "/Items?\$filter={$filter_encoded}&\$select={$select}&\$orderby=ItemCode&\$top={$top}&\$skip={$skip}";
        $response = $client->get($query);
        $client->logout();

        if (!isset($response['value'])) {
            // Algunos SAP no soportan todos los filtros, intentar sin Valid
            return ['error' => __('No se pudieron obtener los artículos de SAP.', 'sapwoo'), 'raw' => $response];
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

        error_log('[SAPWC] Ejecutando sincronización automática de productos');
        $result = self::import_all(['update_existing' => true, 'default_status' => 'publish']);
        error_log('[SAPWC] Resultado productos: ' . ($result['message'] ?? json_encode($result)));
    }
}
