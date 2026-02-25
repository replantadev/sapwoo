<?php
/**
 * Sincronización de Categorías SAP → WooCommerce
 * 
 * Importa ItemGroups de SAP como categorías de producto en WooCommerce.
 * Soporta importación por lotes (batch) para operaciones asíncronas.
 *
 * @package SAPWC
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAPWC_Category_Sync
{
    /**
     * Obtiene todos los ItemGroups desde SAP
     *
     * @param int $skip  Offset para paginación
     * @param int $top   Tamaño de lote
     * @return array ['items' => [...], 'has_more' => bool, 'total_fetched' => int]
     */
    public static function fetch_from_sap($skip = 0, $top = 50)
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

        $query = "/ItemGroups?\$select=Number,GroupName&\$orderby=GroupName&\$top={$top}&\$skip={$skip}";
        $response = $client->get($query);
        $client->logout();

        if (!isset($response['value'])) {
            return ['error' => __('No se pudieron obtener los grupos de artículos.', 'sapwoo')];
        }

        return [
            'items'         => $response['value'],
            'has_more'      => count($response['value']) === $top,
            'total_fetched' => count($response['value']),
        ];
    }

    /**
     * Obtiene TODOS los ItemGroups (sin paginación, para UI de tabla)
     *
     * @return array Lista de ItemGroups
     */
    public static function fetch_all_from_sap()
    {
        $all = [];
        $skip = 0;
        $top = 100;

        do {
            $result = self::fetch_from_sap($skip, $top);

            if (isset($result['error'])) {
                return $result;
            }

            $all = array_merge($all, $result['items']);
            $skip += $top;
        } while ($result['has_more'] && $skip < 5000);

        return ['items' => $all, 'total' => count($all)];
    }

    /**
     * Verifica si una categoría SAP ya existe en WooCommerce
     *
     * @param int    $group_number Number de ItemGroup en SAP
     * @param string $group_name   Nombre del grupo
     * @return int|false Term ID si existe, false si no
     */
    public static function category_exists_in_woo($group_number, $group_name = '')
    {
        // Buscar por meta sapwc_sap_group_number
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'meta_key'   => 'sapwc_sap_group_number',
            'meta_value' => $group_number,
            'hide_empty' => false,
            'number'     => 1,
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            return $terms[0]->term_id;
        }

        // Fallback: buscar por nombre exacto
        if (!empty($group_name)) {
            $term = get_term_by('name', $group_name, 'product_cat');
            if ($term) {
                // Asociar el meta para futuras búsquedas
                update_term_meta($term->term_id, 'sapwc_sap_group_number', $group_number);
                return $term->term_id;
            }
        }

        return false;
    }

    /**
     * Importa una categoría individual desde datos de SAP
     *
     * @param array $group_data ['Number' => int, 'GroupName' => string]
     * @return array Resultado de la importación
     */
    public static function import_category($group_data)
    {
        $number = $group_data['Number'] ?? null;
        $name   = trim($group_data['GroupName'] ?? '');

        if ($number === null || empty($name)) {
            return ['success' => false, 'message' => __('Datos de grupo incompletos.', 'sapwoo')];
        }

        // Verificar si ya existe
        $existing_id = self::category_exists_in_woo($number, $name);
        if ($existing_id) {
            // Actualizar nombre si cambió
            $existing_term = get_term($existing_id, 'product_cat');
            if ($existing_term && $existing_term->name !== $name) {
                wp_update_term($existing_id, 'product_cat', ['name' => $name]);
            }

            return [
                'success' => true,
                'action'  => 'updated',
                'term_id' => $existing_id,
                'name'    => $name,
            ];
        }

        // Crear la categoría
        $slug = sanitize_title($name);
        $result = wp_insert_term($name, 'product_cat', [
            'slug' => $slug,
        ]);

        if (is_wp_error($result)) {
            // Si el slug ya existe, intentar con número SAP
            if ($result->get_error_code() === 'term_exists') {
                $existing_term_id = $result->get_error_data('term_exists');
                update_term_meta($existing_term_id, 'sapwc_sap_group_number', $number);

                return [
                    'success' => true,
                    'action'  => 'linked',
                    'term_id' => $existing_term_id,
                    'name'    => $name,
                ];
            }

            return [
                'success' => false,
                'message' => $result->get_error_message(),
            ];
        }

        $term_id = $result['term_id'];

        // Guardar meta de referencia SAP
        update_term_meta($term_id, 'sapwc_sap_group_number', $number);
        update_term_meta($term_id, 'sapwc_imported_at', current_time('mysql'));

        return [
            'success' => true,
            'action'  => 'created',
            'term_id' => $term_id,
            'name'    => $name,
        ];
    }

    /**
     * Importa un lote de categorías (para operación asíncrona)
     *
     * @param int $skip  Offset
     * @param int $batch Tamaño de lote
     * @return array Resultado del lote
     */
    public static function import_batch($skip = 0, $batch = 50)
    {
        $fetch = self::fetch_from_sap($skip, $batch);

        if (isset($fetch['error'])) {
            return ['success' => false, 'message' => $fetch['error']];
        }

        $results = [
            'created' => 0,
            'updated' => 0,
            'linked'  => 0,
            'errors'  => 0,
            'details' => [],
        ];

        foreach ($fetch['items'] as $group) {
            $result = self::import_category($group);

            if ($result['success']) {
                $results[$result['action']]++;
            } else {
                $results['errors']++;
            }

            $results['details'][] = $result;
        }

        $results['has_more'] = $fetch['has_more'];
        $results['next_skip'] = $skip + $batch;
        $results['success'] = true;
        $results['message'] = sprintf(
            __('Lote procesado: %d creadas, %d actualizadas, %d vinculadas, %d errores', 'sapwoo'),
            $results['created'],
            $results['updated'],
            $results['linked'],
            $results['errors']
        );

        return $results;
    }

    /**
     * Importa TODAS las categorías de una vez
     *
     * @return array
     */
    public static function import_all()
    {
        if (get_transient('sapwc_category_sync_lock')) {
            return ['success' => false, 'locked' => true, 'message' => __('Importación ya en curso.', 'sapwoo')];
        }

        set_transient('sapwc_category_sync_lock', 1, 10 * MINUTE_IN_SECONDS);

        $totals = ['created' => 0, 'updated' => 0, 'linked' => 0, 'errors' => 0];
        $skip = 0;
        $batch = 50;

        try {
            do {
                $result = self::import_batch($skip, $batch);

                if (!$result['success']) {
                    delete_transient('sapwc_category_sync_lock');
                    return $result;
                }

                $totals['created'] += $result['created'];
                $totals['updated'] += $result['updated'];
                $totals['linked']  += $result['linked'];
                $totals['errors']  += $result['errors'];

                $skip = $result['next_skip'];
            } while ($result['has_more']);

            update_option('sapwc_categories_last_sync', current_time('mysql'));

            $msg = sprintf(
                __('Categorías sincronizadas: %d creadas, %d actualizadas, %d vinculadas, %d errores', 'sapwoo'),
                $totals['created'],
                $totals['updated'],
                $totals['linked'],
                $totals['errors']
            );

            SAPWC_Logger::log(null, 'category_sync', 'success', $msg);

            return array_merge($totals, [
                'success'   => true,
                'message'   => $msg,
                'last_sync' => current_time('mysql'),
            ]);
        } finally {
            delete_transient('sapwc_category_sync_lock');
        }
    }

    /**
     * Obtiene el mapeo actual SAP GroupNumber → WooCommerce Term ID
     *
     * @return array [group_number => term_id, ...]
     */
    public static function get_mapping()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT term_id, meta_value as group_number 
             FROM {$wpdb->termmeta} 
             WHERE meta_key = 'sapwc_sap_group_number'",
            ARRAY_A
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row['group_number']] = (int) $row['term_id'];
        }

        return $map;
    }

    /**
     * Estadísticas de categorías sincronizadas
     *
     * @return array
     */
    public static function get_stats()
    {
        global $wpdb;

        $imported = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = 'sapwc_sap_group_number'"
        );

        $total_woo = wp_count_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);

        return [
            'total_imported' => (int) $imported,
            'total_woo'      => (int) $total_woo,
            'last_sync'      => get_option('sapwc_categories_last_sync', __('Nunca', 'sapwoo')),
        ];
    }

    /**
     * Callback del cron automático
     */
    public static function cron_callback()
    {
        if (get_option('sapwc_sync_categories_auto', '0') !== '1') {
            return;
        }

        error_log('[SAPWC] Ejecutando sincronización automática de categorías');
        $result = self::import_all();
        error_log('[SAPWC] Resultado categorías: ' . ($result['message'] ?? json_encode($result)));
    }
}
