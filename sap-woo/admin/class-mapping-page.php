<?php
class SAPWC_Mapping_Page
{
    public static function render()
    {
        if (isset($_POST['sapwc_map'])) {
            $map = array_map('sanitize_text_field', $_POST['sapwc_map']);
            update_option('sapwc_field_mapping', $map);
            echo '<div class="updated"><p>Mapeo actualizado.</p></div>';
        }

        $mapping = get_option('sapwc_field_mapping', []);
        $woo_fields = [
            'sku' => 'SKU (get_sku)',
            'name' => 'Nombre del producto (get_name)',
            'quantity' => 'Cantidad (get_quantity)',
            'price' => 'Precio (get_price)'
        ];

        $sap_suggestions = [
            'ItemCode',
            'ItemDescription',
            'Quantity',
            'UnitPrice',
            'TaxCode',
            'CardCode',
            'CardName',
            'DocDate',
            'DocDueDate',
            'Comments'
        ];

        echo '<div class="wrap"><h1>Mapeo de Campos Woo ⇄ SAP</h1>';
        echo '<form method="post">';
        echo '<table class="widefat fixed striped">
            <thead><tr><th>Campo WooCommerce</th><th>Campo SAP <small>(autocompletado)</small></th></tr></thead><tbody>';

        foreach ($woo_fields as $key => $label) {
            $value = esc_attr($mapping[$key] ?? '');
            echo '<tr>
                <td>' . esc_html($label) . '</td>
                <td><input type="text" name="sapwc_map[' . esc_attr($key) . ']" value="' . $value . '" list="sap-suggestions" class="regular-text"></td>
            </tr>';
        }

        echo '</tbody></table>';
        echo '<datalist id="sap-suggestions">';
        foreach ($sap_suggestions as $suggestion) {
            echo '<option value="' . esc_attr($suggestion) . '">';
        }
        echo '</datalist>';
        submit_button('Guardar mapeo de campos');
        echo '</form>';

        echo '<hr><h2>Sincronización masiva de pedidos</h2>';
        echo '<form method="post">';
        echo '<p><label><input type="checkbox" name="sapwc_force_resend" value="1"> Forzar reenvío incluso si ya fueron enviados</label></p>';
        submit_button('Enviar todos los pedidos a SAP');
        echo '</form>';

        if (isset($_POST['sapwc_force_resend']) || isset($_POST['sapwc_sync_all'])) {
            $force = isset($_POST['sapwc_force_resend']);

            $args = [
                'limit' => -1,
                'status' => 'processing'
            ];

            if (!$force) {
                $args['meta_query'] = [[
                    'key' => '_sap_exported',
                    'compare' => 'NOT EXISTS'
                ]];
            }

            $orders = wc_get_orders($args);

            // Obtener conexión activa
            $conn = sapwc_get_active_connection();

            if (!$conn) {
                wp_send_json_error(['message' => 'No hay conexión SAP activa.']);
            }

            $client = new SAPWC_API_Client($conn['url']);
            $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

            if (!$login['success']) {
                wp_send_json_error(['message' => 'Error de conexión SAP: ' . $login['message']]);
            }

            $sync  = new SAPWC_Sync_Handler($client);;

            $sent = 0;
            $skipped = 0;

            foreach ($orders as $order) {
                if (!$force && get_post_meta($order->get_id(), '_sap_exported', true)) {
                    $skipped++;
                    continue;
                }

                $billing_country = $order->get_billing_country();
                $card_code = ($billing_country === 'ES' || $billing_country === 'PT') ? 'CLIENTESWEBNADP' : 'CLIENTESNADC';

                $sync->set_custom_card_code($card_code);
                $result = $sync->send_order($order);
                if ($result['success']) {
                    $sent++;
                }
            }

            echo '<div class="updated"><p>✅ Se enviaron ' . $sent . ' pedidos a SAP.';
            if ($skipped > 0) echo ' (' . $skipped . ' ya habían sido enviados)';
            echo '</p></div>';
        }

        echo '<hr><h2>📑 Campos disponibles desde SAP (/Orders)</h2>';
        echo '<form method="post" style="display:inline-block;margin-right:1em;"><input type="hidden" name="sapwc_metadata_check" value="1">';
        submit_button('Consultar campos desde SAP');
        echo '</form>';

        echo '<form method="post" style="display:inline-block;"><input type="hidden" name="sapwc_force_login" value="1">';
        submit_button('🔄 Reiniciar sesión con SAP');
        echo '</form>';

        if (isset($_POST['sapwc_force_login'])) {
            $conn = sapwc_get_active_connection();
            if (!$conn) {
                echo '<div class="error"><p>❌ No hay conexión SAP activa.</p></div>';
                return;
            }
        
            $client = new SAPWC_API_Client($conn['url']);
            $result  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
        
            if ($result['success']) {
                echo '<div class="updated"><p>✅ Sesión reiniciada correctamente con SAP.</p></div>';
            } else {
                echo '<div class="error"><p>❌ Error al reiniciar sesión: ' . esc_html($result['message']) . '</p></div>';
            }
        }
        

        if (isset($_POST['sapwc_metadata_check'])) {
            $conn = sapwc_get_active_connection();
            if (!$conn) {
                echo '<div class="error"><p>❌ No hay conexión SAP activa.</p></div>';
                return;
            }

            $client = new SAPWC_API_Client($conn['url']);
            $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

            if (!$client->get_cookie_header()) {
                echo '<div class="error"><p>⚠️ Sesión inválida o expirada. Por favor, revisa tus credenciales o vuelve a iniciar sesión.</p></div>';
                return;
            }

            $url = untrailingslashit($client->get_base_url()) . '/$metadata';
            $response = wp_remote_get($url, [
                'headers' => [
                    'Cookie' => $client->get_cookie_header(),
                    'Accept' => 'application/xml'
                ],
                'timeout' => 30,
                'sslverify' => !empty($this->conn['ssl'])
            ]);

            if (is_wp_error($response)) {
                echo '<div class="error"><p>Error al obtener metadata: ' . esc_html($response->get_error_message()) . '</p></div>';
            } else {
                $body = wp_remote_retrieve_body($response);
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($body);

                if (!$xml) {
                    echo '<div class="error"><p>Error al parsear el XML de metadata.</p></div>';
                } else {
                    echo '<h3>🧩 Entidades y campos disponibles en SAP</h3>';
                    echo '<div style="max-height:400px;overflow:auto;border:1px solid #ccc;padding:1em;background:#fff">';
                    foreach ($xml->children('edmx', true)->DataServices->children() as $schema) {
                        foreach ($schema->EntityType as $entity) {
                            echo '<strong>' . $entity['Name'] . '</strong><ul>';
                            foreach ($entity->Property as $prop) {
                                echo '<li>' . $prop['Name'] . ' (' . $prop['Type'] . ')</li>';
                            }
                            echo '</ul>';
                        }
                    }
                    echo '</div><hr>';
                }
                echo '<h3>📄 XML completo (sin procesar)</h3>';
                echo '<textarea readonly style="width:100%;height:400px;font-family:monospace;">' . esc_html($body) . '</textarea>';
            }
        }
    }
}







add_action('wp_ajax_sapwc_save_mapping', function () {

    check_ajax_referer('sapwc_nonce', 'nonce');



    $mapping = array_map('sanitize_text_field', $_POST['mapping']);

    update_option('sapwc_field_mapping', $mapping);



    wp_send_json_success('Mapeo guardado.');
});

add_action('wp_ajax_sapwc_get_mapping', function () {

    check_ajax_referer('sapwc_nonce', 'nonce');



    $mapping = get_option('sapwc_field_mapping', []);

    wp_send_json_success($mapping);
});
