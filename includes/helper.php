<?php
//helper de funciones comunes
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
//Funcion de conexiones:
/**
 * Obtiene la conexión activa de SAP desde las opciones del plugin.
 *
 * @return array|null La conexión activa o null si no está configurada.
 */
function sapwc_get_active_connection()
{
    $all_connections = get_option('sapwc_connections', []);
    $index = get_option('sapwc_connection_index', 0);

    if (!isset($all_connections[$index])) {
        error_log(__('❌ No hay conexión activa configurada en SAP Woo Sync.', 'sapwoo'));
        return null;
    }

    $connection = $all_connections[$index];

    // Asegurar que el campo 'ssl' esté seteado (por compatibilidad)
    if (!isset($connection['ssl'])) {
        $connection['ssl'] = false;
    }

    if (empty($connection['url']) || empty($connection['user']) || empty($connection['pass']) || empty($connection['db'])) {
        error_log(__('❌ Conexión activa incompleta: ', 'sapwoo') . print_r($connection, true));
        return null;
    }

    return $connection;
}

function sapwc_build_orders_query()
{
    $mode = get_option('sapwc_mode', 'ecommerce');
    $query_info = [
        'mode' => $mode,
        'query' => '',
        'params' => [],
        'filter_after_php' => false,
    ];

    if ($mode === 'ecommerce') {
        $peninsula = sanitize_text_field(get_option('sapwc_cardcode_peninsula', 'WNAD PENINSULA'));
        $canarias  = sanitize_text_field(get_option('sapwc_cardcode_canarias', 'WNAD CANARIAS'));
        $portugal  = sanitize_text_field(get_option('sapwc_cardcode_portugal', 'WWEB PORTUGAL'));

        $query_info['params'] = compact('peninsula', 'canarias', 'portugal');
        $query_info['query'] = "/Orders?\$filter=(CardCode eq '$peninsula' or CardCode eq '$canarias' or CardCode eq '$portugal')&\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
    } elseif ($mode === 'b2b') {
        $filter_type  = get_option('sapwc_customer_filter_type', 'starts');
        $filter_value = sanitize_text_field(trim(get_option('sapwc_customer_filter_value', '')));

        $query_info['params'] = compact('filter_type', 'filter_value');

        if (!empty($filter_value)) {
            if ($filter_type === 'starts' || $filter_type === 'prefix_numbers') {
                // En ambos casos usamos startswith en SAP
                $query_info['query'] = "/Orders?\$filter=startswith(CardCode,'$filter_value')&\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";

                // En prefix_numbers, luego filtramos en PHP
                if ($filter_type === 'prefix_numbers') {
                    $query_info['filter_after_php'] = true;
                }
            } elseif ($filter_type === 'contains') {
                $query_info['query'] = "/Orders?\$filter=contains(CardCode,'$filter_value')&\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
            }
        } else {
            $query_info['query'] = "/Orders?\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
        }
    } else {
        $query_info['query'] = "/Orders?\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
    }

    return $query_info;
}

/**
 * Mapea los estados de SAP a los estados de WooCommerce.
 *
 * @return array Mapeo de estados de SAP a WooCommerce.
 */
function get_valid_wc_state($sap_code)
{
    $reverse_map = array_flip(get_valid_sap_state_map());
    return $reverse_map[$sap_code] ?? null;
}
function get_valid_sap_state_map()
{
    return [
        'AL' => '04',
        'CA' => '11',
        'CO' => '14',
        'GR' => '18',
        'H' => '21',
        'JA' => '23',
        'MA' => '29',
        'SE' => '41',
        'HU' => '22',
        'TE' => '44',
        'Z' => '50',
        'O' => '33',
        'AS' => '33',
        'PM' => '07',
        'GC' => '35',
        'TF' => '38',
        'CN' => '35',
        'S' => '39',
        'AV' => '05',
        'BU' => '09',
        'LE' => '24',
        'P' => '34',
        'SA' => '37',
        'SG' => '40',
        'SO' => '42',
        'VA' => '47',
        'ZA' => '49',
        'AB' => '02',
        'CR' => '13',
        'CU' => '16',
        'GU' => '19',
        'TO' => '45',
        'B' => '08',
        'GI' => '17',
        'L' => '25',
        'T' => '43',
        'CE' => '51',
        'ML' => '52',
        'A' => '03',
        'CS' => '12',
        'V' => '46',
        'BA' => '06',
        'CC' => '10',
        'C' => '15',
        'LU' => '27',
        'OR' => '32',
        'OU' => '32',
        'PO' => '36',
        'M' => '28',
        'MU' => '30',
        'NA' => '31',
        'BI' => '48',
        'SS' => '20',
        'VI' => '01',
        'LO' => '26'
    ];
}

function get_valid_sap_state($wc_state)
{
    $wc_state = strtoupper(trim($wc_state));

    $state_map = [
        // Andalucía
        'AL' => '04',
        'CA' => '11',
        'CO' => '14',
        'GR' => '18',
        'H'  => '21',
        'JA' => '23',
        'MA' => '29',
        'SE' => '41',

        // Aragón
        'HU' => '22',
        'TE' => '44',
        'Z'  => '50',

        // Asturias
        'O'  => '33',
        'AS' => '33',

        // Islas Baleares
        'PM' => '07',

        // Canarias
        'GC' => '35',
        'TF' => '38',
        'CN' => '35', // 'CN' es común en Woo para Canarias

        // Cantabria
        'S'  => '39',

        // Castilla y León
        'AV' => '05',
        'BU' => '09',
        'LE' => '24',
        'P' => '34',
        'SA' => '37',
        'SG' => '40',
        'SO' => '42',
        'VA' => '47',
        'ZA' => '49',

        // Castilla-La Mancha
        'AB' => '02',
        'CR' => '13',
        'CU' => '16',
        'GU' => '19',
        'TO' => '45',

        // Cataluña
        'B'  => '08',
        'GI' => '17',
        'L'  => '25',
        'T' => '43',

        // Ceuta y Melilla
        'CE' => '51',
        'ML' => '52',

        // Comunidad Valenciana
        'A'  => '03',
        'CS' => '12',
        'V'  => '46',

        // Extremadura
        'BA' => '06',
        'CC' => '10',

        // Galicia
        'C'  => '15',
        'LU' => '27',
        'OR' => '32',
        'OU' => '32',
        'PO' => '36',

        // Madrid
        'M'  => '28',

        // Murcia
        'MU' => '30',

        // Navarra
        'NA' => '31',

        // País Vasco
        'BI' => '48',
        'SS' => '20',
        'VI' => '01',

        // La Rioja
        'LO' => '26'
    ];

    return $state_map[$wc_state] ?? null;
}


/**
 * Devuelve el IVA de la clase de impuesto del producto, o el 21% si no encuentra.
 */
function sapwc_get_tax_rate_percent($tax_class = '')
{
    if ($tax_class === '') $tax_class = 'standard';
    $taxes = WC_Tax::get_rates($tax_class);
    $iva_percent = 0;
    if ($taxes) {
        $tax_obj = reset($taxes);
        if (isset($tax_obj['rate'])) {
            $iva_percent = (float) $tax_obj['rate'];
        }
    }
    // Fallback manual: buscar en tabla tasas estándar
    if ($iva_percent <= 0 && $tax_class === 'standard') {
        global $wpdb;
        $rate = $wpdb->get_var(
            "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = '' ORDER BY tax_rate_id LIMIT 1"
        );
        if ($rate !== null) {
            $iva_percent = (float) $rate;
        }
    }
    // Si aún así nada, fuerza 21%
    if ($iva_percent <= 0) {
        $iva_percent = 21;
    }
    return $iva_percent;
}