<?php

class SAPWC_Sync_Handler
{
    private $client;
    private $mapping;

    public function __construct($client)
    {
        $this->client = $client;
        $this->mapping = get_option('sapwc_field_mapping', []);
    }

    public function set_custom_card_code($code)
    {
        $this->card_code = $code;
    }

    public function send_order($order)
    {
        if (!isset($this->mapping['sku'], $this->mapping['name'], $this->mapping['quantity'], $this->mapping['price'])) {
            return ['success' => false, 'message' => 'Mapeo de campos incompleto. Verifica los ajustes.'];
        }

        $order_number = $order->get_order_number();

        if ($order->get_meta('_sap_exported')) {
            $existing = $this->check_order_in_sap($order_number);
            if ($existing && isset($existing['DocEntry'])) {
                $order->update_meta_data('_sap_docentry', $existing['DocEntry']);
                $order->save();
                return ['success' => false, 'message' => 'Pedido ya fue enviado a SAP'];
            }
        }

        $mode = get_option('sapwc_mode', 'ecommerce');
        $payload = $mode === 'b2b' ? $this->build_payload_b2b($order) : $this->build_payload_ecommerce($order);

        if (!$payload) {
            $this->store_order_fallback($order->get_id(), 'Error al generar el payload.');
            return ['success' => false, 'message' => 'Error al generar el payload.'];
        }

        error_log("\n========== ENVÍO A SAP ==========\nPedido ID: {$order->get_id()}\nPayload enviado:\n" . json_encode($payload, JSON_PRETTY_PRINT));

        $endpoint = untrailingslashit($this->client->get_base_url()) . '/Orders';

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Cookie'       => $this->client->get_cookie_header()
            ],
            'body'       => json_encode($payload),
            'timeout'    => 60,
            'sslverify' => !empty($this->conn['ssl'])
        ]);

        if (is_wp_error($response)) {
            $msg = $response->get_error_message();
            $order->add_order_note('❌ Error al enviar a SAP: ' . $msg);

            $order->save();
            $this->store_order_fallback($order->get_id(), $msg);
            SAPWC_Logger::log($order->get_id(), 'sync', 'error', 'Error HTTP: ' . $response->get_error_message());

            return ['success' => false, 'message' => $msg];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 201 && isset($body['DocEntry'])) {
            $sap_id = $body['DocEntry'];
            $order->update_meta_data('_sap_exported', true);
            $order->update_meta_data('_sap_docentry', $sap_id);
            $order->add_order_note('✅ Pedido enviado a SAP. DocEntry: ' . $sap_id);
            $this->add_shipping_address_to_bp($payload['CardCode'], $order);
            $order->update_meta_data('_sap_address_synced', true);
            $order->save();
            update_option('sapwc_orders_last_sync', current_time('mysql'));
            update_option('sapwc_orders_last_docentry', $sap_id);
            SAPWC_Logger::log($order->get_id(), 'sync', 'success', 'Pedido sincronizado', $sap_id);

            if (empty($body['Comments'])) {
                $patch_data = [
                    'DocDate'    => current_time('Y-m-d'),
                    'DocDueDate' => current_time('Y-m-d'),
                    'Comments'   => $payload['Comments']
                ];

                $patch_endpoint = untrailingslashit($this->client->get_base_url()) . "/Orders($sap_id)";
                $patch_response = wp_remote_request($patch_endpoint, [
                    'method' => 'PATCH',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Cookie' => $this->client->get_cookie_header()
                    ],
                    'body' => json_encode($patch_data),
                    'timeout' => 30,
                    'sslverify' => !empty($this->conn['ssl'])
                ]);

                if (is_wp_error($patch_response)) {
                    $order->add_order_note('❌ Error al hacer PATCH para actualizar el comentario: ' . $patch_response->get_error_message());
                } else {
                    $patch_code = wp_remote_retrieve_response_code($patch_response);
                    $patch_body = wp_remote_retrieve_body($patch_response);

                    if ($patch_code === 204) {
                        $order->add_order_note('🔄 Comentario actualizado correctamente con PATCH.');
                    } else {
                        $decoded_patch = json_decode($patch_body, true);
                        $patch_error = $decoded_patch['error']['message']['value'] ?? $patch_body ?? 'Error desconocido en PATCH';
                        $order->add_order_note('⚠️ El comentario no pudo actualizarse con PATCH. ' . $patch_error);
                        SAPWC_Logger::log($order->get_id(), 'patch', 'warning', 'Comentario vacío, se intentó PATCH.', $sap_id);
                    }
                }
            }

            return ['success' => true, 'message' => 'Pedido enviado a SAP con éxito.', 'docentry' => $sap_id];
        }

        $error = $body['error']['message']['value'] ?? json_encode($body) ?? 'Error desconocido';
        SAPWC_Logger::log($order->get_id(), 'sync', 'error', 'Error al enviar a SAP: ' . $error);
        $order->add_order_note('❌ Error al enviar a SAP: ' . $error);
        $order->save();
        $this->store_order_fallback($order->get_id(), $error);
        error_log("[ERROR SAP] Código $code - Respuesta: " . print_r($body, true));

        return ['success' => false, 'message' => $error];
    }

    private function store_order_fallback($order_id, $reason = '')
    {
        $fallbacks = get_option('sapwc_failed_orders', []);
        $fallbacks[$order_id] = [
            'timestamp' => current_time('mysql'),
            'reason'    => $reason
        ];
        update_option('sapwc_failed_orders', $fallbacks);
    }

    private function build_items($order)
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) continue;

            $sku = $product->get_sku();
            $line_subtotal = $item->get_total();
            $quantity      = $item->get_quantity();
            $price_excl_tax = $quantity > 0 ? ($line_subtotal / $quantity) : 0;

            $items[] = [
                'ItemCode'        => $sku,
                'ItemDescription' => $product->get_name(),
                'Quantity'        => $quantity,
                'UnitPrice'       => round($price_excl_tax, 4),
                'WarehouseCode'   => '01'
            ];
        }
        return $items;
    }

    private function build_payload_b2b($order)
    {
        $user = $order->get_user();
        $meta_key = get_option('sapwc_b2b_cardcode_meta', 'user_login');
        $card_code = $meta_key === 'user_login' ? $user->user_login : get_user_meta($user->ID, $meta_key, true);
        if (!$card_code) return false;

        $order_number = $order->get_order_number();
        $items = $this->build_items($order);
        if (empty($items)) return false;

        $billing_phone  = $order->get_billing_phone();
        $billing_dni    = $order->get_meta('billing_dni');
        $billing_email  = $order->get_billing_email();
        $billing_name   = $order->get_formatted_billing_full_name();

        $comments = "$order_number | $billing_name | $billing_email | $billing_phone";

        return [
            'CardCode'      => $card_code,
            'CardName'      => $billing_name,
            'DocDate'       => current_time('Y-m-d'),
            'DocDueDate'    => current_time('Y-m-d'),
            'TaxDate'       => current_time('Y-m-d'),
            'NumAtCard'     => $order_number,
            'Comments'      => $comments,
            'DocumentLines' => $items,
            'UserFields'    => [
                'U_DNI' => $billing_dni
            ]
        ];
    }

    private function build_payload_ecommerce($order)
    {
        $billing_name     = $order->get_formatted_billing_full_name();
        $billing_phone    = $order->get_billing_phone();
        $billing_email    = $order->get_billing_email();
        $billing_dni      = $order->get_meta('billing_dni');
        $order_number     = $order->get_order_number();

        $billing_address = [
            'street'  => $order->get_billing_address_1(),
            'zip'     => $order->get_billing_postcode(),
            'city'    => $order->get_billing_city(),
            'state'   => $order->get_billing_state(),
            'country' => $order->get_billing_country(),
        ];

        $shipping_address = [
            'name'    => $order->get_formatted_shipping_full_name(),
            'street'  => $order->get_shipping_address_1(),
            'zip'     => $order->get_shipping_postcode(),
            'city'    => $order->get_shipping_city(),
            'state'   => $order->get_shipping_state(),
            'country' => $order->get_shipping_country(),
        ];

        $entrega_distinta = $shipping_address['street'] && $shipping_address['street'] !== $billing_address['street'];
        $entrega_address = $entrega_distinta ? $shipping_address : $billing_address;
        $entrega_nombre  = $entrega_distinta ? $shipping_address['name'] : $billing_name;

        $entrega_full = trim("{$entrega_address['street']} {$entrega_address['zip']} {$entrega_address['city']} ({$entrega_address['state']}, {$entrega_address['country']})");

        $cupones = $order->get_coupon_codes();
        $cupones_str = $cupones ? implode(', ', $cupones) : 'ninguno';
        $observaciones = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            $sku = $product->get_sku();
            $regular = $product->get_regular_price();
            $sale = $product->get_sale_price();
            if ($sale && $sale < $regular) {
                $observaciones[] = "SKU $sku en oferta: {$sale}€ (normal: {$regular}€)";
            }
        }

        $final_observ = "DNI: $billing_dni | Cupones: $cupones_str | " . implode(' | ', $observaciones);

        $billing_state = strtoupper($billing_address['state']);
        $card_code = in_array($billing_state, ['GC', 'TF', 'LP', 'HI', 'TE', 'CN'])
            ? get_option('sapwc_cardcode_canarias', 'WNAD CANARIAS')
            : get_option('sapwc_cardcode_peninsula', 'WNAD PENINSULA');
        $card_name = in_array($billing_state, ['GC', 'TF', 'LP', 'HI', 'TE', 'CN'])
            ? get_option('sapwc_cardname_canarias', 'CLIENTEWEBNAD CANARIAS')
            : get_option('sapwc_cardname_peninsula', 'CLIENTEWEBNAD PENINSULA');

        $comments = "WEB NAD+ $order_number | $entrega_nombre | $entrega_full | Dirección de correo electrónico: {$billing_email} | Teléfono: {$billing_phone}";

        return [
            'CardCode'      => $card_code,
            'CardName'      => $card_name,
            'DocDate'       => current_time('Y-m-d'),
            'DocDueDate'    => current_time('Y-m-d'),
            'TaxDate'       => current_time('Y-m-d'),
            'NumAtCard'     => $order_number,
            'Comments'      => mb_substr($comments, 0, 254),
            'DocumentLines' => $this->build_items($order),
            'UserFields'    => [
                'U_ARTES_Com'         => 'CLIENTE WEB NAD',
                'U_ARTES_TEL'         => $billing_phone,
                'U_ARTES_Portes'      => 'P',
                'U_ARTES_Ruta'        => 45,
                'U_ARTES_Alerta'      => 'CLIENTE WEB NAD',
                'U_PerFact'           => 'V',
                'U_DRA_Observ_Agencia' => 'WEB-' . $order_number,
                'U_DNI'               => $billing_dni,
                'U_ARTES_Observ'      => mb_substr($final_observ, 0, 254),
                'U_DRA_Coment_Alm'    => mb_substr($comments, 0, 254),
            ]
        ];
    }

    private function check_order_in_sap($order_number)
    {
        $query = "/Orders?\$filter=NumAtCard eq '$order_number'";
        $result = $this->client->get($query);

        if (isset($result['value'][0])) {
            return $result['value'][0];
        }
        return null;
    }







    private function add_shipping_address_to_bp($card_code, $order)
    {
        $order_number = $order->get_order_number();
        $address_id   = 'WEB-' . $order_number;

        $endpoint = untrailingslashit($this->client->get_base_url()) . "/BusinessPartners('$card_code')";

        // Obtener el BusinessPartner actual
        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Accept'  => 'application/json',
                'Cookie'  => $this->client->get_cookie_header()
            ],
            'timeout'    => 30,
            'sslverify'  => false
        ]);

        if (is_wp_error($response)) {
            $msg = '[BP PATCH] Error al obtener el BP ' . $card_code . ': ' . $response->get_error_message();
            error_log($msg);
            SAPWC_Logger::log($order->get_id(), 'bp_patch', 'error', $msg);
            return;
        }

        $bp_data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($bp_data)) {
            error_log("[BP PATCH] Respuesta malformada al obtener BP $card_code");
            return;
        }

        // Construimos la nueva dirección
        $new_address = [
            'AddressName' => $address_id,
            'AddressType' => 'bo_ShipTo',
            'Street'      => $order->get_shipping_address_1(),
            'ZipCode'     => $order->get_shipping_postcode(),
            'City'        => $order->get_shipping_city(),
            'State'       => $order->get_shipping_state(),
            'Country'     => $order->get_shipping_country()
        ];

        $existing_addresses = $bp_data['BPAddresses'] ?? [];
        $found_index = null;

        // Buscar si ya existe
        foreach ($existing_addresses as $i => $addr) {
            if ($addr['AddressName'] === $address_id) {
                $found_index = $i;
                break;
            }
        }

        if ($found_index !== null) {
            // Ya existe → Comparamos si hay cambios
            $has_changes = false;
            foreach ($new_address as $key => $value) {
                if (trim($existing_addresses[$found_index][$key] ?? '') !== trim($value)) {
                    $has_changes = true;
                    break;
                }
            }

            if (!$has_changes) {
                error_log("[BP PATCH] Dirección $address_id ya existe y está actualizada.");
                return;
            }

            // Modificar la dirección existente
            $existing_addresses[$found_index] = array_merge($existing_addresses[$found_index], $new_address);
        } else {
            // Añadir nueva dirección
            $existing_addresses[] = $new_address;
        }

        // Preparar PATCH
        $patch_body = json_encode(['BPAddresses' => $existing_addresses], JSON_UNESCAPED_UNICODE);
        $patch_response = wp_remote_request($endpoint, [
            'method'  => 'PATCH',
            'headers' => [
                'Content-Type' => 'application/json',
                'Cookie'       => $this->client->get_cookie_header()
            ],
            'body'       => $patch_body,
            'timeout'    => 30,
            'sslverify'  => false
        ]);

        if (is_wp_error($patch_response)) {
            $msg = '[BP PATCH] Error HTTP al actualizar dirección: ' . $patch_response->get_error_message();
            error_log($msg);
            SAPWC_Logger::log($order->get_id(), 'bp_patch', 'error', $msg);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($patch_response);
        if ($status_code === 204) {
            $msg = "[BP PATCH] Dirección $address_id añadida o modificada correctamente en BP $card_code.";
            error_log($msg);
            SAPWC_Logger::log($order->get_id(), 'bp_patch', 'success', $msg);
        } else {
            $body = wp_remote_retrieve_body($patch_response);
            $msg = "[BP PATCH] Error al aplicar PATCH ($status_code): $body";
            error_log($msg);
            SAPWC_Logger::log($order->get_id(), 'bp_patch', 'error', $msg);
        }
    }
}

// Mostrar menú solo para gestores de tienda y superiores
add_action('admin_menu', function () {
    if (current_user_can('edit_others_shop_orders')) {
        add_menu_page(
            'SAP WC Pedidos',
            'SAP Woo',
            'edit_others_shop_orders',
            'sapwc_orders',
            'SAPWC_Orders_Page::render',
            'dashicons-randomize',
            56
        );
    }
}, 9);

add_action('wp_ajax_sapwc_send_order', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');
    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Pedido no encontrado.');
    }
    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db']);

    if (!$login['success']) {
        wp_send_json_error(['message' => '❌ Error al conectar con SAP: ' . $login['message']]);
    }

    $sync_handler = new SAPWC_Sync_Handler($client);
    $result = $sync_handler->send_order($order);
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
    wp_die();
});

add_action('wp_ajax_sapwc_test_connection', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');
    $url = sanitize_text_field($_POST['url']);
    $user = sanitize_text_field($_POST['user']);
    $pass = sanitize_text_field($_POST['pass']);
    $db   = sanitize_text_field($_POST['db']);
    $client = new SAPWC_API_Client($url);
    $response = $client->login($user, $pass, $db);
    if ($response['success']) {
        wp_send_json_success('Conexión exitosa.');
    } else {
        wp_send_json_error('Error de conexión: ' . $response['message']);
    }
});

add_filter('manage_edit-shop_order_columns', function ($columns) {
    $columns['sap_address'] = '📍 Dirección SAP';
    return $columns;
});

add_action('manage_shop_order_posts_custom_column', function ($column, $post_id) {
    if ($column === 'sap_address') {
        $ok = get_post_meta($post_id, '_sap_address_synced', true);
        echo $ok ? '<span style="color:green;">✔</span>' : '–';
    }
}, 10, 2);



//pedidos fallidos:
add_action('wp_ajax_sapwc_retry_failed_order', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Pedido no encontrado.']);
    }

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db']);

    if (!$login['success']) {
        wp_send_json_error(['message' => '❌ Error al conectar con SAP: ' . $login['message']]);
    }

    $sync = new SAPWC_Sync_Handler($client);
    $result = $sync->send_order($order);

    if ($result['success']) {
        $failed = get_option('sapwc_failed_orders', []);
        unset($failed[$order_id]);
        update_option('sapwc_failed_orders', $failed);
        wp_send_json_success(['message' => 'Pedido reenviado con éxito.']);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});
