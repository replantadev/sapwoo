<?php
if (!defined('ABSPATH')) exit;
class SAPWC_Sync_Options_Page
{
    public static function render()
    {
        $mode                  = get_option('sapwc_mode', 'ecommerce');
        $sync_orders_auto      = get_option('sapwc_sync_orders_auto', '0');
        $orders_last_sync      = get_option('sapwc_orders_last_sync', 'Nunca');
        $orders_last_doc       = get_option('sapwc_orders_last_docentry', '–');

        $sync_stock_auto       = get_option('sapwc_sync_stock_auto', '0');
        $selected_tariff       = get_option('sapwc_selected_tariff', '');
        $selected_warehouses   = get_option('sapwc_selected_warehouses', ['01']);
        $stock_last_sync       = get_option('sapwc_stock_last_sync', 'Nunca');

        $cardcode_peninsula    = get_option('sapwc_cardcode_peninsula', 'WNAD PENINSULA');
        $cardname_peninsula    = get_option('sapwc_cardname_peninsula', 'CLIENTEWEBNAD PENINSULA');
        $cardcode_canarias     = get_option('sapwc_cardcode_canarias', 'WNAD CANARIAS');
        $cardname_canarias     = get_option('sapwc_cardname_canarias', 'CLIENTEWEBNAD CANARIAS');
        $b2b_cardcode_meta     = get_option('sapwc_b2b_cardcode_meta', 'user_login');
        $retry_failed_auto     = get_option('sapwc_retry_failed_auto', '0');
        $cron_interval         = get_option('sapwc_cron_interval', 'hourly');
?>
        <div class="wrap sapwc-settings">
            <h1>⚙️ Configuración de Sincronización SAP</h1>

            <form method="post" action="options.php">
                <?php
                if (!current_user_can('manage_options')) {
                    wp_die(__('No tienes suficientes permisos para acceder a esta página.'));
                }
                if (isset($_POST['_wpnonce']) && !wp_verify_nonce($_POST['_wpnonce'], 'sapwc_sync_settings')) {
                    wp_die(__('Nonce inválido.'));
                }
                settings_fields('sapwc_sync_settings');
                do_settings_sections('sapwc_sync_settings');
                settings_errors();
                if (isset($_GET['settings-updated'])) {
                    add_settings_error('sapwc_sync_settings', 'settings_updated', __('Configuración guardada.'), 'updated');
                }
                ?>
                <p>Configura la sincronización automática de pedidos y stock entre WooCommerce y SAP Business One.</p>

                <table class="form-table widefat striped">
                    <tr>
                        <th colspan="2">
                            <h2>🛠️ Modo de Operación</h2>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">Modo</th>
                        <td>
                            <select name="sapwc_mode" id="sapwc_mode">
                                <option value="ecommerce" <?php selected($mode, 'ecommerce'); ?>>Ecommerce (Cliente genérico)</option>
                                <option value="b2b" <?php selected($mode, 'b2b'); ?>>B2B (Cliente individual con su CardCode)</option>
                            </select>
                        </td>
                    </tr>
                    <?php if ($mode === 'b2b') : ?>
                        <tr>
                            <th scope="row">Campo meta del usuario para CardCode</th>
                            <td>
                                <input type="text" name="sapwc_b2b_cardcode_meta" value="<?php echo esc_attr($b2b_cardcode_meta); ?>" class="regular-text" placeholder="user_login, billing_company, etc">
                                <p class="description">Meta del usuario que se usará como <code>CardCode</code>. Por defecto: <code>user_login</code></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
                <details style="margin-bottom:2em">
                    <summary><strong>⚙️ Configuración Avanzada</strong></summary>
                    <table class="form-table widefat striped">
                        <tr>
                            <th colspan="2">
                                <h3>📆 Intervalo de Cron</h3>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row">Frecuencia</th>
                            <td>
                                <select name="sapwc_cron_interval">
                                    <option value="hourly" <?php selected($cron_interval, 'hourly'); ?>>Cada hora</option>
                                    <option value="twicedaily" <?php selected($cron_interval, 'twicedaily'); ?>>Cada 12 horas</option>
                                    <option value="daily" <?php selected($cron_interval, 'daily'); ?>>Diario</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="2">
                                <h3>📁 Reintentos Automáticos</h3>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row">Reintentar pedidos fallidos</th>
                            <td>
                                <label><input type="checkbox" name="sapwc_retry_failed_auto" value="1" <?php checked($retry_failed_auto, '1'); ?>> Habilitar</label>
                            </td>
                        </tr>
                    </table>
                </details>


                <div class="sapwc-settings-columns">
                    <div class="sapwc-column">
                        <table class="form-table widefat striped">
                            <tr>
                                <th colspan="2">
                                    <h2>🗁 Sincronización de Pedidos</h2>
                                </th>
                            </tr>
                            <tr>
                                <th scope="row">Modo de sincronización</th>
                                <td>
                                    <label class="sapwc-toggle">
                                        <input type="checkbox" id="sapwc_sync_orders_auto" name="sapwc_sync_orders_auto" value="1" <?php checked($sync_orders_auto, '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span style="margin-left: 1em;">Automática</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Última sincronización</th>
                                <td><strong id="sapwc-last-sync"><?php echo esc_html($orders_last_sync); ?></strong></td>
                            </tr>
                            <tr>
                                <th scope="row">Último DocEntry sincronizado</th>
                                <td><strong id="sapwc-last-docentry"><?php echo esc_html($orders_last_doc); ?></strong></td>
                            </tr>
                            <?php if ($mode === 'ecommerce') : ?>
                                <tr>
                                    <th colspan="2">
                                        <h3>🗽 Cliente Ecommerce (NAD)</h3>
                                    </th>
                                </tr>
                                <tr>
                                    <th scope="row">Cliente Península</th>
                                    <td>
                                        <input type="text" name="sapwc_cardcode_peninsula" value="<?php echo esc_attr($cardcode_peninsula); ?>" class="regular-text" placeholder="CardCode">
                                        <input type="text" name="sapwc_cardname_peninsula" value="<?php echo esc_attr($cardname_peninsula); ?>" class="regular-text" placeholder="Nombre">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Cliente Canarias</th>
                                    <td>
                                        <input type="text" name="sapwc_cardcode_canarias" value="<?php echo esc_attr($cardcode_canarias); ?>" class="regular-text" placeholder="CardCode">
                                        <input type="text" name="sapwc_cardname_canarias" value="<?php echo esc_attr($cardname_canarias); ?>" class="regular-text" placeholder="Nombre">
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="sapwc-column">
                        <table class="form-table widefat striped">
                            <tr>
                                <th colspan="2">
                                    <h2>📊 Sincronización de Stock y Precios</h2>
                                </th>
                            </tr>
                            <tr>
                                <th scope="row">Modo de sincronización</th>
                                <td>
                                    <label class="sapwc-toggle">
                                        <input type="checkbox" id="sapwc_sync_stock_auto" name="sapwc_sync_stock_auto" value="1" <?php checked($sync_stock_auto, '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span style="margin-left: 1em;">Automática</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Tarifa base (lista de precios)</th>
                                <td>
                                    <input type="text" name="sapwc_selected_tariff" value="<?php echo esc_attr($selected_tariff); ?>" placeholder="Ej: 01 o LI" class="regular-text">
                                    <p class="description">Código de la tarifa usada como base para los precios.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Almacenes seleccionados</th>
                                <td>
                                    <label><input type="checkbox" name="sapwc_selected_warehouses[]" value="01" <?php checked(in_array('01', $selected_warehouses)); ?>> 01</label><br>
                                    <label><input type="checkbox" name="sapwc_selected_warehouses[]" value="LI" <?php checked(in_array('LI', $selected_warehouses)); ?>> LI (Liquidación)</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Última sincronización de stock</th>
                                <td><strong><?php echo esc_html($stock_last_sync); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button('📂 Guardar cambios'); ?>
            </form>

            <hr>
            <h2>🔄 Sincronización Manual</h2>
            <p>Si necesitas forzar la sincronización de pedidos o stock, puedes hacerlo desde aquí:</p>
            <p>
                <button id="sapwc-sync-orders" class="button button-primary">🛫 Sincronizar Pedidos</button>
                <button id="sapwc-sync-stock" class="button button-secondary">📦 Sincronizar Stock</button>
            </p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                function toggleOption(id, key) {
                    $('#' + id).on('change', function() {
                        $.post(ajaxurl, {
                            action: 'sapwc_toggle_option',
                            nonce: sapwc_ajax.nonce,
                            key: key,
                            value: this.checked ? '1' : '0'
                        }, function(response) {
                            if (response.success) {
                                $('<div class="notice notice-success is-dismissible"><p>✅ Configuración guardada.</p></div>')
                                    .insertAfter('.wrap h1').delay(2000).fadeOut();
                            } else {
                                alert('❌ Error al guardar');
                            }
                        });
                    });
                }

                toggleOption('sapwc_sync_orders_auto', 'sapwc_sync_orders_auto');
                toggleOption('sapwc_sync_stock_auto', 'sapwc_sync_stock_auto');

                $('#sapwc-sync-orders').on('click', function() {
                    $.post(ajaxurl, {
                        action: 'sapwc_send_orders',
                        nonce: sapwc_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            $('<div class="notice notice-success is-dismissible"><p>✅ Sincronización de pedidos completada.</p></div>')
                                .insertAfter('.wrap h1').delay(2000).fadeOut();
                        } else {
                            alert('❌ Error al sincronizar: ' + response.data);
                        }
                    });
                });

                $('#sapwc-sync-stock').on('click', function() {
                    $.post(ajaxurl, {
                        action: 'sapwc_sync_stock_now',
                        nonce: sapwc_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            $('<div class="notice notice-success is-dismissible"><p>✅ Stock actualizado correctamente.</p></div>')
                                .insertAfter('.wrap h1').delay(2000).fadeOut();
                        } else {
                            alert('❌ Error al sincronizar stock: ' + response.data);
                        }
                    });
                });
            });
        </script>
<?php
    }
}

add_action('wp_ajax_sapwc_toggle_option', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $key = sanitize_text_field($_POST['key']);
    $value = $_POST['value'] === '1' ? '1' : '0';

    if (!in_array($key, ['sapwc_sync_orders_auto', 'sapwc_sync_stock_auto'])) {
        wp_send_json_error('Clave no válida');
    }

    update_option($key, $value);
    wp_send_json_success('Guardado');
    wp_die();
});

add_action('wp_ajax_sapwc_sync_stock_now', 'sapwc_sync_stock_items');

add_action('admin_init', function () {
    register_setting('sapwc_sync_settings', 'sapwc_mode');
    register_setting('sapwc_sync_settings', 'sapwc_b2b_cardcode_meta');
    register_setting('sapwc_sync_settings', 'sapwc_sync_orders_auto');
    register_setting('sapwc_sync_settings', 'sapwc_orders_last_sync');
    register_setting('sapwc_sync_settings', 'sapwc_orders_last_docentry');
    register_setting('sapwc_sync_settings', 'sapwc_sync_stock_auto');
    register_setting('sapwc_sync_settings', 'sapwc_selected_tariff');
    register_setting('sapwc_sync_settings', 'sapwc_selected_warehouses');
    register_setting('sapwc_sync_settings', 'sapwc_stock_last_sync');

    register_setting('sapwc_sync_settings', 'sapwc_cardcode_peninsula');
    register_setting('sapwc_sync_settings', 'sapwc_cardname_peninsula');
    register_setting('sapwc_sync_settings', 'sapwc_cardcode_canarias');
    register_setting('sapwc_sync_settings', 'sapwc_cardname_canarias');
    register_setting('sapwc_sync_settings', 'sapwc_retry_failed_auto');
    register_setting('sapwc_sync_settings', 'sapwc_cron_interval');
});

add_action('sapwc_cron_sync_stock', 'sapwc_sync_stock_items');


add_filter('cron_schedules', function ($schedules) {
    $schedules['twicedaily'] = ['interval' => 43200, 'display' => 'Cada 12 horas'];
    return $schedules;
});

add_action('init', function () {
    $interval = get_option('sapwc_cron_interval', 'hourly');

    if (wp_next_scheduled('sapwc_cron_sync_orders')) {
        wp_clear_scheduled_hook('sapwc_cron_sync_orders');
    }
    wp_schedule_event(time() + 60, $interval, 'sapwc_cron_sync_orders');

    if (wp_next_scheduled('sapwc_cron_sync_stock')) {
        wp_clear_scheduled_hook('sapwc_cron_sync_stock');
    }
    wp_schedule_event(time() + 90, $interval, 'sapwc_cron_sync_stock');
});
add_action('update_option_sapwc_cron_interval', function ($old, $new) {
    if ($old !== $new) {
        if (wp_next_scheduled('sapwc_cron_sync_orders')) {
            wp_clear_scheduled_hook('sapwc_cron_sync_orders');
        }
        wp_schedule_event(time() + 60, $new, 'sapwc_cron_sync_orders');
    }
}, 10, 2);

function sapwc_sync_stock_items()
{
    if (get_option('sapwc_sync_stock_auto') !== '1') return;

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db']);

    if (!$login['success']) return;


    $tarifa = get_option('sapwc_selected_tariff');
    $almacenes = get_option('sapwc_selected_warehouses', ['01']);

    if (!$tarifa || empty($almacenes)) return;

    $query = "/Items?$select=ItemCode,ItemName,ItemPrices,ItemWarehouseInfoCollection";
    $response = $client->get($query);
    if (!isset($response['value'])) return;

    $prices_include_tax = get_option('woocommerce_prices_include_tax') === 'yes';

    foreach ($response['value'] as $item) {
        $sku = $item['ItemCode'];
        $precio = null;
        $stock_total = 0;

        foreach ($item['ItemPrices'] as $price) {
            if ((string)$price['PriceList'] === $tarifa) {
                $precio = (float)$price['Price'];
                break;
            }
        }

        foreach ($item['ItemWarehouseInfoCollection'] as $wh) {
            if (in_array($wh['WarehouseCode'], $almacenes)) {
                $stock_total += (float)$wh['InStock'];
            }
        }

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) continue;

        $post_id = is_object($product_id) ? $product_id->get_id() : $product_id;
        $price_final = $precio;

        if ($prices_include_tax && $precio !== null) {
            $product = wc_get_product($post_id);
            $tax_class = $product->get_tax_class();
            $tax_rates = WC_Tax::get_rates($tax_class);

            if (!empty($tax_rates)) {
                $rate = reset($tax_rates);
                if (isset($rate['rate'])) {
                    $tax_multiplier = 1 + ((float)$rate['rate'] / 100);
                    $price_final = round($precio * $tax_multiplier, 2);
                }
            }
        }

        update_post_meta($post_id, '_regular_price', $price_final);
        update_post_meta($post_id, '_price', $price_final);
        update_post_meta($post_id, '_sapwc_price_net', $precio); // Guarda el neto original de SAP
        update_post_meta($post_id, '_sapwc_stock_total', $stock_total);
        update_post_meta($post_id, '_stock', $stock_total);
        update_post_meta($post_id, '_stock_quantity', $stock_total);
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_stock_status', $stock_total > 0 ? 'instock' : 'outofstock');
    }

    update_option('sapwc_stock_last_sync', current_time('mysql'));
}


if (!wp_next_scheduled('sapwc_cron_sync_stock')) {
    wp_schedule_event(time() + 120, 'hourly', 'sapwc_cron_sync_stock');
}
