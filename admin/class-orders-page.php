<?php
if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}
// Evitar carga directa del archivo
if (!defined('SAPWC_PLUGIN_PATH')) {
    define('SAPWC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
require_once SAPWC_PLUGIN_PATH . 'includes/helper.php';
class SAPWC_Orders_Page
{
    public static function render()
    {
        $orders = wc_get_orders([
            'status' => ['processing', 'on-hold'],
            'limit'  => 20,
        ]);

        echo '<div class="wrap"><h1>Pedidos en procesamiento</h1>';
        // Coloca esto justo después de: echo '<div class="wrap"><h1>Pedidos en procesamiento</h1>';
        $query_data = sapwc_build_orders_query();
        $mode_label = strtoupper($query_data['mode']) === 'B2B' ? 'B2B (clientes individuales)' : 'Ecommerce (cliente genérico)';
        echo '<div class="notice notice-info" style="padding: 15px 20px; margin: 20px 0;">';
        echo '<p><strong>🔍 Modo actual:</strong> ' . esc_html($mode_label) . '</p>';

        if ($query_data['mode'] === 'ecommerce') {
            echo '<p><strong>🧾 Clientes consultados:</strong> ';
            echo '<code>' . esc_html($query_data['params']['peninsula']) . '</code> y ';
            echo '<code>' . esc_html($query_data['params']['canarias']) . '</code></p>';
        } elseif ($query_data['mode'] === 'b2b') {
          ;
            $type_label = '';

            if ($query_data['params']['filter_type'] === 'starts') {
                $type_label = 'que empiezan por';
            } elseif ($query_data['params']['filter_type'] === 'contains') {
                $type_label = 'que contienen';
            } elseif ($query_data['params']['filter_type'] === 'prefix_numbers') {
                $type_label = 'que comienzan con el prefijo seguido de números';
            }

            echo '<p><strong>🔤 Filtro aplicado:</strong> Mostrar clientes ' . esc_html($type_label) . ' <code>' . esc_html($query_data['params']['filter_value']) . '</code></p>';
        }

        echo '<details style="margin-top: 10px;"><summary style="cursor: pointer;">📄 Ver consulta SAP</summary>';
        echo '<pre style="white-space: pre-wrap; word-break: break-word; background: #f6f8fa; padding: 10px; margin-top: 8px; border-left: 3px solid #2271b1;">';
        echo esc_html($query_data['query']);
        echo '</pre></details>';
        echo '</div>';

        echo '<button id="sapwc-send-orders" class="button button-primary">🛫 Enviar todos a SAP</button>';
        echo '<span id="sapwc-connection-status" style="margin-left: 1em; font-weight: bold;">Conectando...</span>';
        echo '<p id="sapwc-send-result" style="font-weight: bold; margin-top: 1em;"></p>';

        echo '<table class="widefat fixed striped" id="sapwc-orders-table">';
        echo '<thead><tr>
            <th>ID</th>
            <th>Cliente</th>
            <th>Fecha</th>
            <th>Total</th>
            <th>Estado SAP</th>
            <th>Acción</th>
        </tr></thead><tbody>';

        foreach ($orders as $order) {
            $order->read_meta_data(true);
            $id = $order->get_id();


            $sap_sent = get_post_meta($id, '_sap_exported', true);
            $docentry = get_post_meta($id, '_sap_docentry', true);
            $is_sent = in_array($sap_sent, [true, '1', 1], true);

            if ($is_sent) {
                $status = '✅ Enviado';
                $status .= $docentry ? " (#$docentry)" : ' ⚠️ (sin DocEntry)';
            } else {
                $status = '❌ Pendiente';
            }


            echo "<tr data-order-id='{$id}'>
                <td>$id</td>
                <td>" . esc_html($order->get_formatted_billing_full_name()) . "</td>
                <td>" . esc_html($order->get_date_created()->date('Y-m-d H:i')) . "</td>
                <td>" . wc_price($order->get_total()) . "</td>
                <td class='sapwc-status'>$status</td>
                <td><button class='button sapwc-send-single' data-id='{$id}'>📤 Enviar</button></td>
            </tr>";
        }

        echo '</tbody></table>';

        // Mostrar bloque SAP
        require_once SAPWC_PLUGIN_PATH . 'admin/class-sap-orders-table.php';
        SAPWC_SAP_Orders_Table::render_table_block();

        self::inline_js();
    }

    private static function inline_js()
    {
?>
        <script>
            jQuery(document).ready(function($) {
                function testConnection() {
                    $.post(ajaxurl, {
                        action: 'sapwc_test_connection',
                        nonce: sapwc_ajax.nonce
                    }, function(response) {
                        const el = $('#sapwc-connection-status');
                        if (response.success) {
                            el.text('🟢 Conexión OK');
                        } else {
                            el.text('🔴 Conexión fallida');
                        }
                    });
                }

                testConnection(); // se lanza al cargar
            });
        </script>
<?php
    }
}




add_action('wp_ajax_sapwc_send_order', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Pedido no encontrado.']);
    }

    if ($order->get_meta('_sap_exported')) {
        wp_send_json_error(['message' => 'Pedido ya fue enviado a SAP']);
    }

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => '❌ Error al conectar con SAP: ' . $login['message']]);
    }

    $sync_handler = new SAPWC_Sync_Handler($client);
    $result = $sync_handler->send_order($order);
    if ($result['success']) {
        if (!empty($result['docentry'])) {
            update_option('sapwc_orders_last_docentry', $result['docentry']);
        }
        update_option('sapwc_orders_last_sync', current_time('mysql'));

        wp_send_json_success([
            'message'     => $result['message'],
            'last_sync'   => get_option('sapwc_orders_last_sync'),
            'docentry'    => $result['docentry'] ?? null
        ]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
    wp_die();
});





add_action('wp_ajax_sapwc_test_connection', function () {

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => '❌ Error al conectar con SAP: ' . $login['message']]);
    } else {
        wp_send_json_success('Conexión exitosa.');
    }
});

add_action('wp_ajax_sapwc_logout', function () {

    check_ajax_referer('sapwc_nonce', 'nonce');



    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    //logout



    $client->logout();
});

add_filter('manage_edit-shop_order_columns', function ($columns) {
    $columns['sap_exported'] = 'SAP';
    return $columns;
});

add_action('manage_shop_order_posts_custom_column', function ($column, $post_id) {
    if ($column === 'sap_exported') {
        $exported = get_post_meta($post_id, '_sap_exported', true);
        $docentry = get_post_meta($post_id, '_sap_docentry', true);
        if (in_array($exported, [true, '1', 1], true)) {
            echo '<span style="color:green;font-weight:bold;">✔ Enviado</span>';
            if ($docentry) {
                echo '<br><small>ID: ' . esc_html($docentry) . '</small>';
            } else {
                echo '<br><small style="color:orange;">⚠️ Sin DocEntry</small>';
            }
        } else {
            echo '<span style="color:#999;">–</span>';
        }
    }
}, 10, 2);
