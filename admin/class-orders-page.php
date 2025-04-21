<?php

class SAPWC_Orders_Page
{
    public static function render()
    {
        $orders = wc_get_orders([
            'status' => 'processing',
            'limit'  => 20,
        ]);

        echo '<div class="wrap"><h1>Pedidos en procesamiento</h1>';
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


add_action('wp_ajax_sapwc_get_sap_orders', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => '❌ Error al conectar con SAP: ' . $login['message']]);
    }


    $query = "/Orders?\$filter=(CardCode eq 'WNAD PENINSULA' or CardCode eq 'WNAD CANARIAS')&\$orderby=DocDate desc&\$top=10";
    $response = $client->get($query);
    error_log('[SAP WC] Consulta usada: ' . $query);
    //error_log('[SAP WC] Respuesta: ' . print_r($response, true));
    if (!isset($response['value'])) {
        wp_send_json_error('Error al obtener los pedidos de SAP');
    }

    wp_send_json_success($response['value']);
    wp_die();
});


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

// AJAX: Enviar todos los pedidos sin _sap_exported a SAP
add_action('wp_ajax_sapwc_send_orders', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => '❌ Error al conectar con SAP: ' . $login['message']]);
    }


    $orders = wc_get_orders([
        'status' => 'processing',
        'limit' => -1
    ]);

    require_once plugin_dir_path(__FILE__) . '../includes/class-sap-sync.php';
    $sync = new SAPWC_Sync_Handler($client);

    $sent = 0;
    $skipped = 0;

    foreach ($orders as $order) {
        $already_sent = $order->get_meta('_sap_exported');
        if (!in_array($already_sent, [true, '1', 1], true)) {
            $result = $sync->send_order($order);

            if ($result['success']) {
                $sent++;
            } else {
                $skipped++;
            }
        } else {
            $skipped++;
        }
    }

    wp_send_json_success([
        'message'     => "✅ Se enviaron $sent pedidos a SAP. ($skipped ya estaban enviados o fallaron)",
        'last_sync'   => current_time('mysql')
    ]);
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
