<?php
if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}
// Evitar carga directa del archivo
if (!defined('SAPWC_PLUGIN_PATH')) {
    define('SAPWC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
require_once SAPWC_PLUGIN_PATH . 'includes/helper.php';
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
            SAPWC_Logger::log(
                $order->get_id(),
                'sync',
                'error',
                '❌ Mapeo de campos incompleto. Faltan: ' . json_encode(array_keys($this->mapping))
            );
            error_log('Mapeo actual: ' . print_r(get_option('sapwc_field_mapping'), true));
            return ['success' => false, 'message' => 'Mapeo de campos incompleto. Verifica los ajustes.'];
        }


        $order_number = $order->get_order_number();

        // Revisión doble: meta y consulta en SAP para evitar duplicados nene
        $already_exported = $order->get_meta('_sap_exported');
        $existing_in_sap  = $this->check_order_in_sap($order_number);

        if ($already_exported || ($existing_in_sap && isset($existing_in_sap['DocEntry']))) {
            // Marcar localmente si aún no estaba
            if (!$already_exported && isset($existing_in_sap['DocEntry'])) {
                update_post_meta($order->get_id(), '_sap_exported', '1');
                update_post_meta($order->get_id(), '_sap_docentry', $existing_in_sap['DocEntry']);
            }

            return [
                'success'  => false,
                'skipped'  => true,
                'message'  => 'Pedido ya fue enviado a SAP',
                'docentry' => $existing_in_sap['DocEntry'] ?? null
            ];
        }



        $mode = get_option('sapwc_mode', 'ecommerce');
        $payload = $mode === 'b2b' ? $this->build_payload_b2b($order) : $this->build_payload_ecommerce($order);

        if (!$payload) {
            SAPWC_Logger::log($order->get_id(), 'sync', 'error', '⚠️ Payload vacío o inválido. Puede deberse a falta de SKU o CardCode.');
            $this->store_order_fallback($order->get_id(), 'Error al generar el payload.');
            return ['success' => false, 'message' => 'Error al generar el payload.'];
        }

        // Marcar al cliente B2B como "cliente web" si aún no lo está
        if ($mode === 'b2b') {
            $bp_endpoint = untrailingslashit($this->client->get_base_url()) . "/BusinessPartners('{$payload['CardCode']}')";
            $bp_response = wp_remote_get($bp_endpoint, [
                'headers' => [
                    'Accept'  => 'application/json',
                    'Cookie'  => $this->client->get_cookie_header()
                ],
                'timeout' => 30,
                'sslverify' => false
            ]);

            if (!is_wp_error($bp_response)) {
                $bp_data = json_decode(wp_remote_retrieve_body($bp_response), true);
                $es_cliente_web = $bp_data['U_ARTES_CLIW'] ?? '';

                if (strtoupper($es_cliente_web) !== 'S') {
                    $patch_response = wp_remote_request($bp_endpoint, [
                        'method' => 'PATCH',
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Cookie'       => $this->client->get_cookie_header()
                        ],
                        'body' => json_encode(['U_ARTES_CLIW' => 'S']),
                        'timeout' => 30,
                        'sslverify' => false
                    ]);

                    if (!is_wp_error($patch_response) && wp_remote_retrieve_response_code($patch_response) === 204) {
                        $order->add_order_note('🟢 Cliente B2B marcado como cliente web en SAP.');
                        SAPWC_Logger::log($order->get_id(), 'bp_patch_b2b', 'success', 'Cliente marcado como web (U_ARTES_CLIW = S)');
                    } else {
                        SAPWC_Logger::log($order->get_id(), 'bp_patch_b2b', 'warning', 'No se pudo marcar como cliente web');
                    }
                }
            } else {
                SAPWC_Logger::log($order->get_id(), 'bp_patch_b2b', 'error', 'Error al leer BP para marcar como cliente web');
            }
        }

        //  VALIDACIÓN: asegurar que todos los productos tienen SKU
        foreach ($payload['DocumentLines'] as $line) {
            if (empty($line['ItemCode'])) {
                $msg = 'Producto sin SKU en payload: ' . print_r($line, true);

                error_log('[ERROR] ' . $msg);
                SAPWC_Logger::log(
                    $order->get_id(),
                    'sync',
                    'error',
                    "SKU inválido: producto sin SKU → " . json_encode($line)
                );
                $product_name = $line['ItemDescription'] ?? '[Sin nombre]';
                $order->add_order_note('❌ Producto sin SKU válido: ' . $product_name);
                $order->save();

                
                return [
                    'success' => false,
                    'message' => "❌ Error: el producto \"$product_name\" no tiene SKU válido. Corrige esto antes de enviar a SAP."
                ];
            }
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
            // Solo aplica el ajuste de redondeo si es modo ecommerce
            if ($mode === 'ecommerce') {
           //Nuevo ajuste no enviamos precios     $this->add_rounding_adjustment_if_needed($order, $sap_id);
            }
            //$this->add_rounding_adjustment_if_needed($order, $sap_id);
            update_post_meta($order->get_id(), '_sap_exported', '1');
            update_post_meta($order->get_id(), '_sap_docentry', $sap_id);
            update_post_meta($order->get_id(), '_sap_address_synced', '1');

            $order->add_order_note('✅ Pedido enviado a SAP. DocEntry: ' . $sap_id);
            $order->update_status('processing', '✅ Pedido enviado a SAP. DocEntry: ' . $sap_id);
            $entrega_address = [
                'Street'    => $order->get_shipping_address_1(),
                'ZipCode'   => $order->get_shipping_postcode(),
                'City'      => $order->get_shipping_city(),
                'State'     => $order->get_shipping_state(),
                'Country'   => $order->get_shipping_country()
            ];

            if (get_option('sapwc_mode') === 'b2b') {
                $this->add_shipping_address_to_bp_b2b($payload['CardCode'], $order);
                $order->add_order_note('📦 Dirección B2B añadida a SAP.');
            } else {
                $this->add_shipping_address_to_bp($payload['CardCode'], $order, $entrega_address);
                $order->add_order_note('✅ Dirección de envío añadida a SAP. ID: ' . $payload['CardCode']);
            }

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

    /**
     * Genera las líneas de pedido para SAP, asegurando que los precios enviados sean SIEMPRE sin IVA.
     * Si WooCommerce almacena los precios con IVA incluido, los recalcula a netos por línea.
     * Deja logs si detecta y ajusta precios desde bruto (con IVA) a neto (sin IVA).
     *
     * @param WC_Order $order El pedido de WooCommerce.
     * @return array Las líneas del documento preparadas para SAP.
     */
    private function build_items_old($order)
    {
        $items = [];
        $mode = get_option('sapwc_mode', 'ecommerce');
        $prices_include_tax = get_option('woocommerce_prices_include_tax') === 'yes';
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) continue;

            $sku = trim($product->get_sku());
            $sku_clean = preg_replace('/[^\x20-\x7E]/', '', $sku);
            $quantity = $item->get_quantity();
            $almacen = $product->get_meta('almacen') ?: $product->get_meta('_almacen');
            $warehouse = $almacen ? strtoupper(trim($almacen)) : '01';

            // 1. OBTENER PRECIO REGULAR y SALE (ambos con IVA)
            $regular_price = (float) $product->get_regular_price();
            $sale_price    = (float) $product->get_sale_price();
            $use_sale      = $sale_price && ($sale_price < $regular_price);

            // 2. OBTENER IVA real (con fallback)
            $tax_class = $product->get_tax_class();
            $iva_percent = sapwc_get_tax_rate_percent($tax_class);

            // 3. CALCULAR PVP CON IVA y NETO (el que espera SAP)
            $pvp_con_iva = $use_sale ? $sale_price : $regular_price;
            $pvp_con_iva = round($pvp_con_iva, 4); // aseguremos que el precio bruto esté ya a 4 decimales
            $pvp_neto = ($prices_include_tax && $iva_percent > 0)
                ? round($pvp_con_iva / (1 + ($iva_percent / 100)), 4)
                : round($pvp_con_iva, 4);

            // 4. CALCULAR DESCUENTO (solo si hay oferta real)
            $discount_percent = 0;
            if ($use_sale && $regular_price > 0) {
                $pvp_neto_regular = ($prices_include_tax && $iva_percent > 0)
                    ? $regular_price / (1 + ($iva_percent / 100))
                    : $regular_price;
                $discount_percent = round((($pvp_neto_regular - $pvp_neto) / $pvp_neto_regular) * 100, 2);
                if ($discount_percent < 0) $discount_percent = 0;
            }

            // 5. CONSTRUIR LÍNEA para SAP
            $line = [
                'ItemCode'        => $sku_clean,
                'ItemDescription' => $product->get_name(),
                'Quantity'        => $quantity,
                'UnitPrice'       => round($pvp_neto, 4), // Aseguramos que el precio neto esté a 4 decimales
                'WarehouseCode'   => $warehouse,
            ];
            if ($discount_percent > 0) {
                $line['UserFields'] = [
                    'U_ARTES_DtoAR1' => $discount_percent
                ];
            }

            // LOG para auditoría (quítalo si no quieres ruido)
            error_log("[BUILD_ITEMS] SKU: $sku_clean | MODE: $mode | ALMACÉN: $warehouse | PVP NETO: $pvp_neto | PVP IVA: $pvp_con_iva | REGULAR: $regular_price | SALE: $sale_price | IVA: $iva_percent | DESC: $discount_percent | QTY: $quantity");

            $items[] = $line;
        }

        return $items;
    }

    private function build_items($order)
    {
        $items = [];

        // 1. Obtener región del pedido para determinar tarifa
        $region_tariff = $this->get_regional_tariff($order);
        $default_tariff = $region_tariff ?: get_option('sapwc_selected_tariff');
        
        $warehouse_tariffs  = get_option('sapwc_warehouse_tariff_map', []);
        if (!is_array($warehouse_tariffs)) {
            $warehouse_tariffs = [];
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) {
                continue;
            }

            $sku       = preg_replace('/[^\x20-\x7E]/', '', trim($product->get_sku()));
            $quantity  = (float) $item->get_quantity();
            $almacen   = $product->get_meta('almacen') ?: $product->get_meta('_almacen');
            $warehouse = strtoupper(trim($almacen ?: '01'));

            // 2. Determinar tarifa aplicable (regional o por almacén)
            $applicable_tariff = $default_tariff;
            if (isset($warehouse_tariffs[$warehouse]) && $warehouse_tariffs[$warehouse]) {
                $applicable_tariff = $warehouse_tariffs[$warehouse];
            }

            // 3. Construir línea SIN precio, usando PriceList
            $line = [
                'ItemCode'      => $sku,
                'ItemDescription' => $product->get_name(),
                'Quantity'      => $quantity,
                'PriceList'     => (int) $applicable_tariff,
                'WarehouseCode' => $warehouse,
            ];

            // Si tienes % de descuento personalizado, lo mantienes en UserFields...
            // if ($discount_percent > 0) {
            //     $line['UserFields'] = ['U_ARTES_DtoAR1' => $discount_percent];
            // }
            
            $items[] = $line;
        }

        return $items;
    }

    private function build_items_with_tariff($order, $specific_tariff)
    {
        $items = [];
        
        // Usar la tarifa específica pasada como parámetro
        $default_tariff = $specific_tariff ?: get_option('sapwc_selected_tariff');
        
        $warehouse_tariffs  = get_option('sapwc_warehouse_tariff_map', []);
        if (!is_array($warehouse_tariffs)) {
            $warehouse_tariffs = [];
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) {
                continue;
            }

            $sku       = preg_replace('/[^\x20-\x7E]/', '', trim($product->get_sku()));
            $quantity  = (float) $item->get_quantity();
            $almacen   = $product->get_meta('almacen') ?: $product->get_meta('_almacen');
            $warehouse = strtoupper(trim($almacen ?: '01'));

            // Determinar tarifa aplicable (específica o por almacén)
            $applicable_tariff = $default_tariff;
            if (isset($warehouse_tariffs[$warehouse]) && $warehouse_tariffs[$warehouse]) {
                $applicable_tariff = $warehouse_tariffs[$warehouse];
            }

            // LOG de debug para verificar la tarifa
            error_log("[BUILD_ITEMS_TARIFF] SKU: {$sku} | Tarifa específica: {$specific_tariff} | Tarifa aplicada: {$applicable_tariff} | Almacén: {$warehouse}");

            // Construir línea SIN precio, usando PriceList
            $line = [
                'ItemCode'      => $sku,
                'ItemDescription' => $product->get_name(),
                'Quantity'      => $quantity,
                'PriceList'     => (int) $applicable_tariff,
                'WarehouseCode' => $warehouse,
            ];
            
            $items[] = $line;
        }

        return $items;
    }

    /**
     * Determina la tarifa regional basada en la dirección de entrega del pedido
     */
    private function get_regional_tariff($order)
    {
        $billing_address = [
            'state'   => $order->get_billing_state(),
            'country' => $order->get_billing_country(),
        ];

        $shipping_address = [
            'street'  => $order->get_shipping_address_1(),
            'state'   => $order->get_shipping_state(),
            'country' => $order->get_shipping_country(),
        ];

        // Usar dirección de envío si es diferente a la de facturación
        $entrega_distinta = $shipping_address['street'] && $shipping_address['street'] !== $order->get_billing_address_1();
        $target_state = $entrega_distinta ? strtoupper($shipping_address['state']) : strtoupper($billing_address['state']);
        $target_country = $entrega_distinta ? strtoupper($shipping_address['country']) : strtoupper($billing_address['country']);
        
        // Determinar tarifa por región - Portugal y Canarias ahora independientes
        if ($target_country === 'PT') {
            // Portugal: usar tarifa específica de Portugal
            return get_option('sapwc_tariff_portugal', '');
        } elseif (in_array($target_state, ['GC', 'TF', 'LP', 'HI', 'TE', 'CN'])) {
            // Canarias: tarifa específica de Canarias
            return get_option('sapwc_tariff_canarias', '');
        } else {
            // Península y Baleares: tarifa específica de península
            return get_option('sapwc_tariff_peninsula', '');
        }
    }




    private function build_items_sin_cargo_old($order)
    {
        $items = [];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) continue;

            $sku         = trim($product->get_sku());
            $sku_clean   = preg_replace('/[^\x20-\x7E]/', '', $sku);
            $quantity    = (int) $item->get_quantity();
            $regular     = (float) $product->get_regular_price();
            $subtotal    = (float) $item->get_total();
            $unit_price  = $quantity > 0 ? round($subtotal / $quantity, 4) : 0;

            $almacen     = $product->get_meta('almacen') ?: $product->get_meta('_almacen');
            $warehouse   = $almacen ? strtoupper(trim($almacen)) : '01';

            // Obtener pack mínimo (orden de prioridad)
            $pack_size = (int) $product->get_meta('compra_minima')
                ?: (int) $product->get_meta('unidades_caja')
                ?: (int) $product->get_meta('_klb_min_quantity')
                ?: (int) $product->get_meta('_klb_step_quantity')
                ?: 0;

            // Ajustar si la cantidad es menor al pack mínimo
            if ($pack_size > 0 && $quantity < $pack_size) {
                error_log("[BUILD_ITEMS_SIN_CARGO] ⚠️ SKU: $sku_clean seleccionó $quantity, ajustado a mínimo $pack_size");
                SAPWC_Logger::log($order->get_id(), 'sync', 'error', sprintf('SKU %s seleccionado con %d uds. Corregido a %d (mínimo)', $sku_clean, $quantity, $pack_size));
                $order->add_order_note("Producto {$product->get_name()} ajustado a $pack_size por mínimo de compra");
                $quantity  = $pack_size;

                if ($regular > 0) {
                    $subtotal  = $regular * $quantity;
                } else {
                    error_log("[BUILD_ITEMS_SIN_CARGO] ❌ Precio regular inválido para SKU $sku_clean");
                    $subtotal = 0;
                }

                $item->set_quantity($quantity);
            }

            // Cálculo de unidades pagadas y regaladas
            $units_paid = $quantity;
            $units_gifted = 0;

            if ($regular > 0 && $subtotal > 0 && $unit_price < $regular) {
                $units_paid = round($subtotal / $regular, 2);
                $units_gifted = max($quantity - $units_paid, 0);

                // Si solo queremos unidades completas regaladas
                $units_gifted = floor($units_gifted);
                $units_paid = $quantity - $units_gifted;

                // Validación suave: si no cuadra, asumir todo pagado
                if (abs(($units_paid + $units_gifted) - $quantity) > 0.1) {
                    error_log("[BUILD_ITEMS_SIN_CARGO] ❌ Ajuste por descuadre decimal en SKU $sku_clean. TOTAL: $quantity ≠ CALCULADAS: " . ($units_paid + $units_gifted));
                    $units_paid = $quantity;
                    $units_gifted = 0;
                } else {
                    error_log("[BUILD_ITEMS_SIN_CARGO] 🎁 Promo detectada SKU $sku_clean → $units_paid pagadas + $units_gifted regaladas");
                }
            }

            $line = [
                'ItemCode'        => $sku_clean,
                'ItemDescription' => $product->get_name(),
                'Quantity'        => $units_paid,
                'UnitPrice'       => round($regular, 4),
                'WarehouseCode'   => $warehouse,
                'U_ARTES_CantSC'  => $units_gifted
            ];

            error_log("[BUILD_ITEMS_SIN_CARGO] SKU: $sku_clean | TOTAL: $quantity | PAGADAS: $units_paid | REGALADAS: $units_gifted");

            $items[] = $line;
        }

        if (empty($items)) {
            SAPWC_Logger::log($order->get_id(), 'sync', 'error', 'Ningún producto válido (con SKU) encontrado para el pedido.');
        }

        return $items;
    }
    private function build_items_sin_cargo($order)
    {
        $items = [];
        // 1. Obtener región del pedido para determinar tarifa
        $region_tariff = $this->get_regional_tariff($order);
        $default_tariff = $region_tariff ?: get_option('sapwc_selected_tariff');
        
        $warehouse_tariffs = get_option('sapwc_warehouse_tariff_map', []);
        if (!is_array($warehouse_tariffs)) {
            $warehouse_tariffs = [];
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->get_sku()) continue;

            $sku         = trim($product->get_sku());
            $sku_clean   = preg_replace('/[^\x20-\x7E]/', '', $sku);
            $quantity    = (int) $item->get_quantity();
            $regular     = (float) $product->get_regular_price();
            $subtotal    = (float) $item->get_total();
            $applicable_tariff = $default_tariff;
            if (isset($warehouse_tariffs[$warehouse]) && $warehouse_tariffs[$warehouse]) {
                $applicable_tariff = $warehouse_tariffs[$warehouse];
            }
           $unit_price  = $quantity > 0 ? round($subtotal / $quantity, 4) : 0;

            $almacen     = $product->get_meta('almacen') ?: $product->get_meta('_almacen');
            $warehouse   = $almacen ? strtoupper(trim($almacen)) : '01';

            // Obtener pack mínimo (orden de prioridad)
            $pack_size = (int) $product->get_meta('compra_minima')
                ?: (int) $product->get_meta('unidades_caja')
                ?: (int) $product->get_meta('_klb_min_quantity')
                ?: (int) $product->get_meta('_klb_step_quantity')
                ?: 0;

            // Ajustar si la cantidad es menor al pack mínimo
            if ($pack_size > 0 && $quantity < $pack_size) {
                error_log("[BUILD_ITEMS_SIN_CARGO] ⚠️ SKU: $sku_clean seleccionó $quantity, ajustado a mínimo $pack_size");
                SAPWC_Logger::log($order->get_id(), 'sync', 'error', sprintf('SKU %s seleccionado con %d uds. Corregido a %d (mínimo)', $sku_clean, $quantity, $pack_size));
                $order->add_order_note("Producto {$product->get_name()} ajustado a $pack_size por mínimo de compra");
                $quantity  = $pack_size;

                if ($regular > 0) {
                    $subtotal  = $regular * $quantity;
                } else {
                    error_log("[BUILD_ITEMS_SIN_CARGO] ❌ Precio regular inválido para SKU $sku_clean");
                    $subtotal = 0;
                }

                $item->set_quantity($quantity);
            }

            // Cálculo de unidades pagadas y regaladas
            $units_paid = $quantity;
            $units_gifted = 0;

            if ($regular > 0 && $subtotal > 0 && $unit_price < $regular) {
                $units_paid = round($subtotal / $regular, 2);
                $units_gifted = max($quantity - $units_paid, 0);

                // Si solo queremos unidades completas regaladas
                $units_gifted = floor($units_gifted);
                $units_paid = $quantity - $units_gifted;

                // Validación suave: si no cuadra, asumir todo pagado
                if (abs(($units_paid + $units_gifted) - $quantity) > 0.1) {
                    error_log("[BUILD_ITEMS_SIN_CARGO] ❌ Ajuste por descuadre decimal en SKU $sku_clean. TOTAL: $quantity ≠ CALCULADAS: " . ($units_paid + $units_gifted));
                    $units_paid = $quantity;
                    $units_gifted = 0;
                } else {
                    error_log("[BUILD_ITEMS_SIN_CARGO] 🎁 Promo detectada SKU $sku_clean → $units_paid pagadas + $units_gifted regaladas");
                }
            }

            $line = [
                'ItemCode'        => $sku_clean,
                'ItemDescription' => $product->get_name(),
                'Quantity'        => $units_paid,
                'PriceList'     => (int) $applicable_tariff,
                'WarehouseCode'   => $warehouse,
                'U_ARTES_CantSC'  => $units_gifted
            ];

            error_log("[BUILD_ITEMS_SIN_CARGO] SKU: $sku_clean | TOTAL: $quantity | PAGADAS: $units_paid | REGALADAS: $units_gifted");

            $items[] = $line;
        }

        if (empty($items)) {
            SAPWC_Logger::log($order->get_id(), 'sync', 'error', 'Ningún producto válido (con SKU) encontrado para el pedido.');
        }

        return $items;
    }

    private function build_payload_b2b($order)
    {
        $user = $order->get_user();

        $meta_key = get_option('sapwc_b2b_cardcode_meta', 'user_login');
        $card_code = $meta_key === 'user_login' ? $user->user_login : get_user_meta($user->ID, $meta_key, true);
        if (is_array($card_code)) {
            $card_code = reset($card_code);
        }

        if (!$card_code || !is_string($card_code)) {
            SAPWC_Logger::log($order->get_id(), 'sync', 'error', 'CardCode no válido: ' . print_r($card_code, true));
            return ['success' => false, 'message' => 'CardCode no válido para el cliente.'];
        }

        $cif_meta_key = get_option('sapwc_b2b_cif_meta', 'nif');
        $billing_dni = trim(get_user_meta($user->ID, $cif_meta_key, true));

        $order_number = $order->get_order_number();
        $discount_mode = get_option('sapwc_discount_mode', 'rebaja');
        $items = ($discount_mode === 'sin_cargo')
            ? $this->build_items_sin_cargo($order)
            : $this->build_items($order);

        if (empty($items)) return false;

        $billing_name = $order->get_formatted_billing_full_name();
        $comments = "Pedido B2B $order_number";

        // Portes y ruta obligatorios
        $u_ruta   = '45';
        $u_portes = 'P';

        // Comercial: primero intentar desde ajustes
        $sales_employee_id = get_option('sapwc_sales_employee_code');
        if (empty($sales_employee_id)) {
            $sales_employee_id = $this->get_sales_employee_code_from_sap($card_code);
        }

        $payload = [
            'CardCode'       => $card_code,
            'CardName'       => $billing_name,
            'DocDate'        => current_time('Y-m-d'),
            'DocDueDate'     => current_time('Y-m-d'),
            'TaxDate'        => current_time('Y-m-d'),
            'NumAtCard'      => $order_number,
            'Comments'       => mb_substr($comments, 0, 254),
            'DocumentLines'  => $items,
            'U_ARTES_Portes' => $u_portes,
            'U_ARTES_Ruta'   => $u_ruta,
        ];
        // 1) Nombre comercial en “Moneda local”
        $billing_company = $order->get_billing_company();
        if (!empty($billing_company)) {
            // recortamos a 254 carácteres si tu UDF lo requiere
            $payload['U_ARTES_Com'] = mb_substr($billing_company, 0, 254);
        }

        // 2) Teléfono de contacto
        $billing_phone = $order->get_billing_phone();
        if (!empty($billing_phone)) {
            $payload['U_ARTES_TEL'] = $billing_phone;
        }
        // 3) DNI
        if (!empty($billing_dni)) {
            $payload['U_DNI'] = $billing_dni;
        }
        
        if (!empty($sales_employee_id)) {
            $payload['SalesPersonCode'] = (int) $sales_employee_id;
        }
        $user_sign = get_option('sapwc_user_sign');
        if (!empty($user_sign)) {
            $payload['DocumentsOwner'] = 97; //sandra a mano
        }


        return $payload;
    }
    private function get_sales_employee_code_from_sap($card_code)
    {
        $relative_path = "/BusinessPartners('{$card_code}')?\$select=SalesPersonCode";

        $response = $this->client->get($relative_path);

        if ($response && isset($response['SalesPersonCode'])) {
            return $response['SalesPersonCode'];
        }

        return null;
    }


    private function build_payload_ecommerce($order)
    {
        $billing_name     = $order->get_formatted_billing_full_name();
        $billing_phone    = $order->get_billing_phone();
        $billing_email    = $order->get_billing_email();
        $billing_dni = $order->get_meta('billing_dni') ?: $order->get_meta('_billing_dni') ?: '';
        $order_number     = $order->get_order_number();
        $u_ruta   = '45';
        $u_portes = 'P';

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
        $customer_note = trim($order->get_customer_note());

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

        // Determinar región y aplicar cliente y tarifa correspondiente
        $billing_state = strtoupper($billing_address['state']);
        $billing_country = strtoupper($billing_address['country']);
        
        // Usar dirección de envío si es diferente
        $target_state = $entrega_distinta ? strtoupper($shipping_address['state']) : $billing_state;
        $target_country = $entrega_distinta ? strtoupper($shipping_address['country']) : $billing_country;
        
        // Lógica de asignación por región - Portugal y Canarias independientes
        if ($target_country === 'PT') {
            // Portugal: usar cliente específico de Portugal con tarifa específica de Portugal
            $card_code = get_option('sapwc_cardcode_portugal', 'WWEB PORTUGAL');
            $card_name = get_option('sapwc_cardname_portugal', 'CLIENTEWEB PORTUGAL');
            $region_tariff = get_option('sapwc_tariff_portugal', '');
            $region_name = 'Portugal';
        } elseif (in_array($target_state, ['GC', 'TF', 'LP', 'HI', 'TE', 'CN'])) {
            // Canarias: tarifa específica de Canarias
            $card_code = get_option('sapwc_cardcode_canarias', 'WNAD CANARIAS');
            $card_name = get_option('sapwc_cardname_canarias', 'CLIENTEWEBNAD CANARIAS');
            $region_tariff = get_option('sapwc_tariff_canarias', '');
            $region_name = 'Canarias';
        } else {
            // Península y Baleares: tarifa específica de península
            $card_code = get_option('sapwc_cardcode_peninsula', 'WNAD PENINSULA');
            $card_name = get_option('sapwc_cardname_peninsula', 'CLIENTEWEBNAD PENINSULA');
            $region_tariff = get_option('sapwc_tariff_peninsula', '');
            $region_name = 'Península/Baleares';
        }
        
        $site_short_name = get_option('sapwc_site_short_name', 'NAD+');
        
        // Incluir ID de dirección SAP en comentarios
        $address_id = 'WEB-' . $order_number;
        $comments = "[$address_id] {$site_short_name} | $order_number | $entrega_nombre | $entrega_full | Email: {$billing_email} | Tel: {$billing_phone}";
        $fecha_creacion = $order->get_date_created();
        $doc_date = $fecha_creacion ? $fecha_creacion->date('Y-m-d') : date('Y-m-d');
        $user_sign = get_option('sapwc_user_sign');
        //if (!empty($user_sign)) {
            $DocumentsOwner = 97; //sandra a mano
        //}

        // LOG para debug regional
        error_log("[REGIONAL_DEBUG] Pedido: {$order_number} | Región: {$region_name} | País: {$target_country} | Estado: {$target_state} | Cliente: {$card_code} | Tarifa: {$region_tariff}");

        return [
            'CardCode'         => $card_code,
            'CardName'         => $card_name,
            'DocDate'          => $doc_date,
            'DocDueDate'       => $doc_date,
            'TaxDate'          => $doc_date,
            'NumAtCard'        => $order_number,
            'Comments'         => mb_substr($comments, 0, 254),
            'U_ARTES_Portes'   => $u_portes,
            'U_ARTES_Ruta'     => strval($u_ruta),
            'DocumentsOwner' => $DocumentsOwner,
            'DocumentLines'    => $this->build_items_with_tariff($order, $region_tariff),
            // Los siguientes solo si estaban antes en tu payload:
            'U_ARTES_Com'         => 'CLIENTE WEB',
            'U_ARTES_TEL'         => $billing_phone,
            'U_ARTES_Alerta'      => 'CLIENTE WEB',
            'U_DRA_Observ_Agencia' => 'WEB-' . $order_number,
            'U_DNI'               => $billing_dni,
            'U_ARTES_Observ'      => mb_substr($final_observ, 0, 254),
            'U_DRA_Coment_Alm'    => mb_substr($comments, 0, 254),
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


    private function add_shipping_address_to_bp($card_code, $order, $address_array)

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
        // Validamos el estado
        $state_value = get_valid_sap_state($order->get_shipping_state());

        // Nota del cliente
        $customer_note = str_replace(["\r", "\n"], ' ', $order->get_customer_note());

        // Dirección base
        $new_address = array_merge([
            'AddressName'         => $address_id,
            'AddressType'         => 'bo_ShipTo',
            'Street'              => $order->get_shipping_address_1(),
            'ZipCode'             => $order->get_shipping_postcode(),
            'City'                => $order->get_shipping_city(),
            'Country'             => $order->get_shipping_country(),
            'BuildingFloorRoom'   => mb_substr($customer_note, 0, 100)
        ], $address_array);

        // Solo añadimos el campo State si es válido
        if ($state_value !== null) {
            $new_address['State'] = $state_value;
        }



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

    private function add_shipping_address_to_bp_b2b($card_code, $order)
    {
        $order_number = $order->get_order_number();
        $address_id   = 'B2B-' . $order_number;

        $endpoint = untrailingslashit($this->client->get_base_url()) . "/BusinessPartners('$card_code')";

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Accept'  => 'application/json',
                'Cookie'  => $this->client->get_cookie_header()
            ],
            'timeout' => 30,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            $msg = '[B2B PATCH] Error al obtener el BP ' . $card_code . ': ' . $response->get_error_message();
            error_log($msg);
            SAPWC_Logger::log($order->get_id(), 'bp_patch_b2b', 'error', $msg);
            return;
        }

        $bp_data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($bp_data)) {
            error_log("[B2B PATCH] Respuesta malformada al obtener BP $card_code");
            return;
        }

        $state_value = get_valid_sap_state($order->get_shipping_state());
        $customer_note = str_replace(["\r", "\n"], ' ', $order->get_customer_note());

        $new_address = [
            'AddressName'       => $address_id,
            'AddressType'       => 'bo_ShipTo',
            'Street'            => $order->get_shipping_address_1(),
            'ZipCode'           => $order->get_shipping_postcode(),
            'City'              => $order->get_shipping_city(),
            'Country'           => $order->get_shipping_country(),
            'BuildingFloorRoom' => mb_substr($customer_note, 0, 100)
        ];

        if ($state_value !== null) {
            $new_address['State'] = $state_value;
        }

        $existing_addresses = $bp_data['BPAddresses'] ?? [];
        $exists = false;

        foreach ($existing_addresses as $addr) {
            $match = true;
            foreach (['Street', 'ZipCode', 'City', 'Country', 'State'] as $key) {
                $existing = strtolower(trim($addr[$key] ?? ''));
                $current = strtolower(trim($new_address[$key] ?? ''));
                if ($existing !== $current) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                $exists = true;
                error_log("[B2B PATCH] Dirección ya existente en BP $card_code, no se modifica.");
                break;
            }
        }

        if ($exists) return;

        // Agregar nueva dirección
        $existing_addresses[] = $new_address;
        $patch_body = json_encode(['BPAddresses' => $existing_addresses], JSON_UNESCAPED_UNICODE);

        $patch_response = wp_remote_request($endpoint, [
            'method' => 'PATCH',
            'headers' => [
                'Content-Type' => 'application/json',
                'Cookie'       => $this->client->get_cookie_header()
            ],
            'body'    => $patch_body,
            'timeout' => 30,
            'sslverify' => false
        ]);

        if (is_wp_error($patch_response)) {
            $msg = '[B2B PATCH] Error HTTP al actualizar dirección: ' . $patch_response->get_error_message();
            error_log($msg);
            SAPWC_Logger::log($order->get_id(), 'bp_patch_b2b', 'error', $msg);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($patch_response);
        if ($status_code === 204) {
            $msg = "[B2B PATCH] Dirección añadida correctamente a BP $card_code.";
            error_log($msg);
            SAPWC_Logger::log($order->get_id(), 'bp_patch_b2b', 'success', $msg);
        } else {
            $body = wp_remote_retrieve_body($patch_response);
            $msg = "[B2B PATCH] Error PATCH ($status_code): $body";
            error_log($msg);
            SAPWC_Logger::log($order->get_id(), 'bp_patch_b2b', 'error', $msg);
        }
    }
    //metodo de ajuste de redondeo
    private function add_rounding_adjustment_if_needed($order, $docentry)
    {
        $order_number = $order->get_order_number();
        $query = "/Orders?\$filter=NumAtCard eq '$order_number'";
        $sap_orders = $this->client->get($query);
        if (!isset($sap_orders['value'][0])) {
            SAPWC_Logger::log($order->get_id(), 'ajuste', 'error', 'No se encontró el pedido en SAP para comparar totales.');
            return;
        }
        $sap_order = $sap_orders['value'][0];
        $sap_total = (float) $sap_order['DocTotal'];

        // Sumar todas las líneas de SAP (productos) con IVA
        $sap_line_total = 0.0;
        foreach ($sap_order['DocumentLines'] as $line) {
            $sap_line_total += ((float)$line['LineTotal'] + (float)$line['TaxTotal']);
        }
        $sap_envio = $sap_total - $sap_line_total;

        $woo_total = (float) $order->get_total();
        $woo_envio = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();

        // Solo compara productos, sin portes
        $ajuste = round(($woo_total - $woo_envio) - ($sap_total - $sap_envio), 2);

        if (abs($ajuste) >= 0.01) {
            // 1) Detectar península vs Canarias
            $country = $order->get_shipping_country();      // 'ES', 'FR', etc.
            $state   = $order->get_shipping_state();        // código de provincia de WooCommerce

            // Provincias canarias según códigos ISO en WooCommerce
            $canarias = ['TF', 'GC', 'LP', 'PM'];           // Santa Cruz de Tenerife, Las Palmas, La Palma, Palmas Minorcas...

            // Por defecto: península
            $vatGroup = 'RE';
            if ($country === 'ES' && in_array($state, $canarias, true)) {
                $vatGroup = 'EX';  // Canarias → exento
            }

            // 2) Preparamos PATCH a SAP
            $endpoint = untrailingslashit($this->client->get_base_url()) . "/Orders($docentry)";
            $document_lines = $sap_order['DocumentLines'];
            $document_lines[] = [
                'ItemCode'        => 'AJUSTE',
                'ItemDescription' => 'Ajuste redondeo e-commerce',
                'Quantity'        => 1,
                'UnitPrice'       => $ajuste,
                'WarehouseCode'   => '01',
                'VatGroup'        => $vatGroup,
            ];

            $patch_data = [
                'DocumentLines' => $document_lines,
                'Comments'      => ($sap_order['Comments'] ?? '') . " | Ajuste de redondeo aplicado $ajuste EUR"
            ];

            $patch_response = wp_remote_request($endpoint, [
                'method' => 'PATCH',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Cookie'       => $this->client->get_cookie_header()
                ],
                'body'    => json_encode($patch_data),
                'timeout' => 30,
                'sslverify' => false
            ]);
            if (is_wp_error($patch_response)) {
                SAPWC_Logger::log($order->get_id(), 'ajuste', 'error', 'Error HTTP al añadir línea de ajuste: ' . $patch_response->get_error_message());
            } else {
                $code = wp_remote_retrieve_response_code($patch_response);
                if ($code === 204) {
                    SAPWC_Logger::log($order->get_id(), 'ajuste', 'success', "Ajuste de redondeo aplicado: $ajuste EUR (IVA: $vatGroup)");
                    $order->add_order_note("🔄 Se ha aplicado un ajuste de redondeo en SAP de $ajuste EUR (IVA: $vatGroup).");
                } else {
                    $body = wp_remote_retrieve_body($patch_response);
                    SAPWC_Logger::log($order->get_id(), 'ajuste', 'error', "Error PATCH ajuste ($code): $body");
                }
            }
        }
    }
}





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
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

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
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

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
