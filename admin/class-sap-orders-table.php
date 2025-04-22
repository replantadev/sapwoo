<?php
// Archivo: admin/class-sap-orders-table.php
if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}
// Evitar carga directa del archivo
if (!defined('SAPWC_PLUGIN_PATH')) {
    define('SAPWC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
require_once SAPWC_PLUGIN_PATH . 'includes/helper.php';
class SAPWC_SAP_Orders_Table
{
    public static function render_table_block()
    {
        $mode = get_option('sapwc_mode', 'ecommerce');
        $message = '';

        if ($mode === 'ecommerce') {
            $name_peninsula = get_option('sapwc_cardname_peninsula', 'CLIENTEWEBNAD PENINSULA');
            $name_canarias = get_option('sapwc_cardname_canarias', 'CLIENTEWEBNAD CANARIAS');

            $message = "📝 Últimos pedidos de: <strong>$name_peninsula</strong> y <strong>$name_canarias</strong>.";
        } else {
            $filter_type = get_option('sapwc_customer_filter_type', 'starts');
            $filter_value = get_option('sapwc_customer_filter_value', '');

            if ($filter_value) {
                $message = $filter_type === 'starts'
                    ? "📝 Últimos pedidos de clientes que empiezan por <code>$filter_value</code>"
                    : "📝 Últimos pedidos de clientes que contienen <code>$filter_value</code>";
            } else {
                $message = "📝 No se ha definido un filtro de clientes. <a href='" . admin_url('options-general.php?page=sapwc-sync-settings') . "'>Definir ahora</a>";
            }
        }

        echo '<h2 style="margin-top: 3em;">' . $message . ' <span id="sapwc-status-indicator" style="margin-left: 10px;">🔴</span></h2>';
        echo '<table class="widefat striped" id="sapwc-sap-orders-table" style="margin-top: 1em; width:100%">';
        echo '<thead><tr>
            <th>' . esc_html__('DocEntry', 'sapwoo') . '</th>
            <th>' . esc_html__('DocNum', 'sapwoo') . '</th>
            <th>' . esc_html__('Fecha', 'sapwoo') . '</th>
            <th>' . esc_html__('Cliente', 'sapwoo') . '</th>
            <th>' . esc_html__('Total', 'sapwoo') . '</th>
            <th>' . esc_html__('Comentarios', 'sapwoo') . '</th>
        </tr></thead><tbody></tbody></table>';
        self::inline_js();
    }

    private static function inline_js()
    {
?>
        <script>
            jQuery(document).ready(function($) {
                function refreshSAPOrders() {
                    $('#sapwc-status-indicator').html('<span class="dashicons dashicons-update" style="color: orange;"></span>');
                    const $table = $('#sapwc-sap-orders-table');
                    $table.find('tbody').html('<tr><td colspan="6">Cargando pedidos desde SAP...</td></tr>');

                    $.ajax({
                        url: sapwc_ajax.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'sapwc_get_sap_orders',
                            nonce: sapwc_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#sapwc-status-indicator').html('<span class="dashicons dashicons-yes" style="color: green;"></span>');

                                const rows = response.data.map(order => {
                                    const [year, month, day] = order.DocDate.split('-');
                                    const formattedDate = `${day}/${month}/${year}`;
                                    return `<tr>
                                        <td>${_.escape(order.DocEntry)}</td>
                                        <td>${_.escape(order.DocNum)}</td>
                                        <td>${_.escape(formattedDate)}</td>
                                        <td>${_.escape(order.CardCode)}</td>
                                        <td>${_.escape(parseFloat(order.DocTotal).toFixed(2))} €</td>
                                        <td>${_.escape(order.Comments || '')}</td>
                                    </tr>`;
                                }).join('');

                                $table.find('tbody').html(rows || '<tr><td colspan="6">No se encontraron resultados.</td></tr>');

                                if ($.fn.DataTable.isDataTable($table)) {
                                    $table.DataTable().clear().destroy();
                                }

                                $table.DataTable({
                                    order: [[0, 'desc']],
                                    pageLength: 10,
                                    language: {
                                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                                    }
                                });

                            } else {
                                $('#sapwc-status-indicator').html('<span class="dashicons dashicons-no-alt" style="color: red;"></span>');
                                $table.find('tbody').html('<tr><td colspan="6">Error: ' + response.data + '</td></tr>');
                            }
                        },
                        error: function() {
                            $('#sapwc-status-indicator').html('<span class="dashicons dashicons-no-alt" style="color: red;"></span>');
                            $table.find('tbody').html('<tr><td colspan="6">Error de red al contactar con SAP.</td></tr>');
                        }
                    });
                }

                refreshSAPOrders();
            });
        </script>
<?php
    }
}



// Hook para manejar solicitudes AJAX y obtener pedidos desde SAP
add_action('wp_ajax_sapwc_get_sap_orders', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => __('❌ No hay conexión activa con SAP.', 'sapwoo')]);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => __('❌ Error al conectar con SAP: ', 'sapwoo') . $login['message']]);
    }

   

    $query_data = sapwc_build_orders_query();
    $query = $query_data['query'];
    $response = $client->get($query);
 
    if (!isset($response['value'])) {
        wp_send_json_error('Error al obtener los pedidos de SAP');
    }
    // Filtro extra si se usa prefix_numbers
    if (!empty($query_data['filter_after_php']) && $query_data['filter_after_php'] === true) {
        $prefix = $query_data['params']['filter_value'];
        $response['value'] = array_filter($response['value'], function ($order) use ($prefix) {
            $suffix = substr($order['CardCode'], strlen($prefix));
            return ctype_digit($suffix); // Solo números después del prefijo
        });

        // Reindexar array
        $response['value'] = array_values($response['value']);
    }
    wp_send_json_success($response['value']);
    wp_die();
});
