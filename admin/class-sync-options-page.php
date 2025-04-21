<?php
if (!defined('ABSPATH')) exit;
class SAPWC_Sync_Options_Page
{
    public static function render()
    {
        $mode                  = get_option('sapwc_mode', 'ecommerce');
        // Comprobación rápida de campos meta necesarios para B2B
        $missing_fields_notice = '';
        if ($mode === 'b2b') {
            global $wpdb;

            $required_meta_keys = ['almacen', 'pvp'];
            $placeholders = implode(',', array_fill(0, count($required_meta_keys), '%s'));

            $query = "
        SELECT pm.meta_key, COUNT(*) as total
        FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE p.post_type IN ('product', 'product_variation')
        AND p.post_status = 'publish'
        AND pm.meta_key IN ($placeholders)
        GROUP BY pm.meta_key
    ";

            $results = $wpdb->get_results($wpdb->prepare($query, ...$required_meta_keys), OBJECT_K);

            $missing = [];
            foreach ($required_meta_keys as $key) {
                if (!isset($results[$key]) || (int)$results[$key]->total === 0) {
                    $missing[] = $key;
                }
            }

            if (!empty($missing)) {
                $missing_fields_notice = '<div class="notice notice-error"><p>❌ Modo B2B: Faltan campos requeridos en productos: <strong>' . implode(', ', $missing) . '</strong>.</p><p>Asegúrate de que todos los productos tengan esos campos meta.</p></div>';
            } else {
                $missing_fields_notice = '<div class="notice notice-success"><p>✅ Todos los productos contienen los campos meta necesarios para el modo B2B.</p></div>';
            }
        }

        $sync_orders_auto      = get_option('sapwc_sync_orders_auto', '0');
        $orders_last_sync      = get_option('sapwc_orders_last_sync', 'Nunca');
        $orders_last_doc       = get_option('sapwc_orders_last_docentry', '–');

        $sync_stock_auto       = get_option('sapwc_sync_stock_auto', '0');
        $selected_tariff       = get_option('sapwc_selected_tariff', '');
        $conn = sapwc_get_active_connection();
        $tariffs = [];

        if ($conn) {
            $client = new SAPWC_API_Client($conn['url']);
            $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
            $tariffs = $client->get_price_lists();
        }

        $selected_warehouses = get_option('sapwc_selected_warehouses', []);
        if (!is_array($selected_warehouses)) {
            $selected_warehouses = explode(',', $selected_warehouses); // fallback si viene como string
        }


        $stock_last_sync       = get_option('sapwc_stock_last_sync', 'Nunca');

        $cardcode_peninsula    = get_option('sapwc_cardcode_peninsula', 'WNAD PENINSULA');
        $cardname_peninsula    = get_option('sapwc_cardname_peninsula', 'CLIENTEWEBNAD PENINSULA');
        $cardcode_canarias     = get_option('sapwc_cardcode_canarias', 'WNAD CANARIAS');
        $cardname_canarias     = get_option('sapwc_cardname_canarias', 'CLIENTEWEBNAD CANARIAS');
        $b2b_cardcode_meta     = get_option('sapwc_b2b_cardcode_meta', 'user_login');
        $retry_failed_auto     = get_option('sapwc_retry_failed_auto', '0');
        $cron_interval         = get_option('sapwc_cron_interval', 'hourly');
        $use_price_after_vat   = get_option('sapwc_use_price_after_vat', '0');

        $customer_filter_type  = get_option('sapwc_customer_filter_type', 'starts'); // "starts" o "contains"
        $customer_filter_value = get_option('sapwc_customer_filter_value', '');

?>
        <div class="wrap sapwc-settings">
            <h1>⚙️ Configuración de Sincronización SAP</h1>
            <?php if (!empty($missing_fields_notice)) echo $missing_fields_notice; ?>
            <p>Configura la sincronización automática de pedidos y stock entre WooCommerce y SAP Business One.</p>

            <form method="post" action="options.php">
                <?php
                if (!current_user_can('edit_others_shop_orders')) {
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
                    echo '<div class="notice notice-success is-dismissible"><p>✅ Configuración guardada correctamente.</p></div>';
                }
                ?>

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
                        <tr>
                            <th scope="row">🧪 Verificación de productos B2B</th>
                            <td>
                                <?php
                                // Recuperar todos los productos y variaciones publicados
                                $productos_b2b = get_posts([
                                    'post_type'   => ['product', 'product_variation'],
                                    'post_status' => 'publish',
                                    'numberposts' => -1,
                                    'meta_query'  => [
                                        ['key' => '_sku', 'compare' => 'EXISTS'], // Asegurarse de que tengan SKU
                                    ],
                                ]);

                                if (empty($productos_b2b)) {
                                    // Si no hay productos publicados
                                    echo '<p><strong style="color:red;">❌ No hay productos publicados para verificar.</strong></p>';
                                } else {
                                    $errores = [];

                                    // Verificar cada producto
                                    foreach ($productos_b2b as $prod) {
                                        $id = $prod->ID;
                                        $title = get_the_title($id);
                                        $missing = [];

                                        // Verificar campos adicionales
                                        if (!get_post_meta($id, '_sku', true)) $missing[] = '_sku';
                                        if (!get_post_meta($id, 'almacen', true)) $missing[] = 'almacen';
                                        if (!get_post_meta($id, 'pvp', true)) $missing[] = 'pvp';

                                        // Si faltan campos, añadir a la lista de errores
                                        if (!empty($missing)) {
                                            $errores[] = "$title (falta: " . implode(', ', $missing) . ")";
                                        }
                                    }

                                    // Mostrar resultados
                                    if (empty($errores)) {
                                        echo '<p><strong style="color:green;">✅ Todos los productos tienen los campos necesarios.</strong></p>';
                                    } else {
                                        echo '<p><strong style="color:red;">❌ ' . count($errores) . ' productos con errores:</strong></p>';
                                        echo '<ul>';
                                        foreach ($errores as $error) {
                                            echo '<li>🔸 ' . esc_html($error) . '</li>';
                                        }
                                        echo '</ul>';
                                    }
                                }
                                ?>
                                <p class="description">Campos necesarios para modo B2B: <code>_sku</code>, <code>almacen</code>, <code>pvp</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Filtro de clientes</th>
                            <td>
                                <?php
                                $filter_type  = get_option('sapwc_customer_filter_type', 'starts');
                                $filter_value = get_option('sapwc_customer_filter_value', '');
                                ?>
                                <select name="sapwc_customer_filter_type">
                                    <option value="starts" <?php selected($filter_type, 'starts'); ?>>CardCode empieza con...</option>
                                    <option value="contains" <?php selected($filter_type, 'contains'); ?>>CardCode contiene...</option>
                                    <option value="prefix_numbers" <?php selected($filter_type, 'prefix_numbers'); ?>>CardCode comienza con prefijo y números...</option>

                                </select>
                                <input type="text" name="sapwc_customer_filter_value" value="<?php echo esc_attr($filter_value); ?>" class="regular-text" placeholder="Ej: WNAD">
                                <p class="description">Filtra los clientes a sincronizar y mostrar en la tabla según su <code>CardCode</code>.</p>
                            </td>
                        </tr>


                    <?php endif; ?>
                </table>



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
                                        <h3>🕓 Estado del Cron de Pedidos</h3>
                                    </th>
                                </tr>
                                <tr>
                                    <th scope="row">Próxima ejecución</th>
                                    <td>
                                        <?php
                                        $next = wp_next_scheduled('sapwc_cron_sync_orders');
                                        if ($next) {
                                            echo '<strong>' . date_i18n('Y-m-d H:i:s', $next) . '</strong>';
                                            echo ' &nbsp;<code>(' . human_time_diff(time(), $next) . ')</code>';
                                        } else {
                                            echo '<span style="color:red;">❌ No programado</span>';
                                        }
                                        ?>
                                        <p><button id="sapwc-run-cron-now" class="button">⏱️ Ejecutar ahora</button></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Frecuencia configurada</th>
                                    <td><strong><?php echo esc_html($cron_interval); ?></strong></td>
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
                        <table class="form-table widefat striped">
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
                                <th scope="row">Almacenes a sincronizar</th>
                                <td>
                                    <label><input type="checkbox" name="sapwc_selected_warehouses[]" value="01"
                                            <?php checked(in_array('01', $selected_warehouses)); ?>> 01 (Principal)</label><br>

                                    <label><input type="checkbox" name="sapwc_selected_warehouses[]" value="LI"
                                            <?php checked(in_array('LI', $selected_warehouses)); ?>> LI (Liquidación)</label><br>

                                    <p class="description">Selecciona los almacenes desde los cuales deseas importar stock.</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Tarifa base (lista de precios)</th>
                                <td>
                                    <select name="sapwc_selected_tariff" class="regular-text">
                                        <option value="">-- Selecciona una tarifa --</option>
                                        <?php foreach ($tariffs as $tariff) : ?>
                                            <option value="<?php echo esc_attr($tariff['id']); ?>" <?php selected($selected_tariff, $tariff['id']); ?>>
                                                <?php echo esc_html($tariff['id'] . ' - ' . $tariff['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($tariffs)) : ?>
                                        <p class="description" style="color:red;"><strong>⚠️ No se pudieron obtener las tarifas desde SAP.</strong> Verifica conexión.</p>
                                    <?php endif; ?>

                                    <p class="description">Código de la tarifa usada como base para los precios.</p>
                                </td>
                            </tr>

                            <tr>
                                <th colspan="2">
                                    <h3>🏷️ Tarifa especial por almacén</h3>
                                </th>
                            </tr>
                            <tr>
                                <th scope="row">Almacén con tarifa distinta</th>
                                <td>
                                    <?php
                                    $warehouse_tariffs_raw = get_option('sapwc_warehouse_tariff_map', []);
                                    $warehouse_tariffs = is_string($warehouse_tariffs_raw)
                                        ? json_decode($warehouse_tariffs_raw, true)
                                        : $warehouse_tariffs_raw;

                                    if (!is_array($warehouse_tariffs)) {
                                        $warehouse_tariffs = [];
                                    }

                                    $almacenes_especiales = ['LI']; // almacenes con tarifa especial opcional

                                    foreach ($almacenes_especiales as $warehouse_code) {
                                        echo '<label for="tariff_map_' . esc_attr($warehouse_code) . '"><strong>' . esc_html($warehouse_code) . '</strong> (Liquidación)</label><br>';
                                        echo '<select name="sapwc_warehouse_tariff_map[' . esc_attr($warehouse_code) . ']" class="regular-text" id="tariff_map_' . esc_attr($warehouse_code) . '">';
                                        echo '<option value="">-- Usar tarifa base --</option>';
                                        foreach ($tariffs as $tariff) {
                                            $selected = selected($warehouse_tariffs[$warehouse_code] ?? '', $tariff['id'], false);
                                            echo '<option value="' . esc_attr($tariff['id']) . '" ' . $selected . '>' . esc_html($tariff['id'] . ' - ' . $tariff['name']) . '</option>';
                                        }
                                        echo '</select><br><br>';
                                    }
                                    ?>
                                    <p class="description">Puedes definir una tarifa específica para productos en el almacén <strong>LI</strong> (Liquidación). Si no se selecciona, se aplicará la tarifa base configurada arriba para todos los productos.</p>
                                    <p class="description">La tarifa base se aplica a todos los productos sin almacén definido o con almacén <code>01</code> (principal).</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Precio desde SAP</th>
                                <td>
                                    <label><input type="checkbox" name="sapwc_use_price_after_vat" value="1" <?php checked($use_price_after_vat, '1'); ?>> Usar <code>PriceAfterVAT</code> si está disponible</label>
                                    <p class="description">Si WooCommerce está configurado con precios con IVA incluido, marca esta opción para usar directamente <code>PriceAfterVAT</code> desde SAP.</p>
                                </td>
                            </tr>


                            <th scope="row">Última sincronización de stock</th>
                            <td><strong id="sapwc-last-stock-sync"><?php echo esc_html($stock_last_sync); ?></strong></td>
                            </tr>
                            <tr>
                                <th colspan="2">
                                    <h3>📦 Estado del Cron de Stock</h3>
                                </th>
                            </tr>
                            <tr>
                                <th scope="row">Próxima ejecución</th>
                                <td>
                                    <?php
                                    $next_stock = wp_next_scheduled('sapwc_cron_sync_stock');
                                    if ($next_stock) {
                                        echo '<strong>' . date_i18n('Y-m-d H:i:s', $next_stock) . '</strong>';
                                        echo ' &nbsp;<code>(' . human_time_diff(time(), $next_stock) . ')</code>';
                                    } else {
                                        echo '<span style="color:red;">❌ No programado</span>';
                                    }
                                    ?>
                                    <p><button id="sapwc-run-stock-now" class="button">📦 Ejecutar ahora</button></p>
                                </td>
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
                <!--<button id="sapwc-sync-stock" class="button button-secondary">📦 Sincronizar Stock</button>-->
                <button id="sapwc-sync-existing" class="button button-primary">📥 Sincronizar Productos Existentes</button>

            </p>
            <?php
            // Hook en el render para incluir nueva sección
            add_action('sapwc_render_price_settings', function () {
                do_action('sapwc_render_price_settings_section');
            }); ?>
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

                function showToast(msg, type = 'success') {
                    const color = type === 'success' ? '#46b450' : '#dc3232';
                    const toast = $('<div>').css({
                        position: 'fixed',
                        top: '50px',
                        right: '30px',
                        background: color,
                        color: '#fff',
                        padding: '12px 20px',
                        borderRadius: '4px',
                        zIndex: 99999,
                        boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
                        fontSize: '14px'
                    }).text(msg);

                    $('body').append(toast);
                    setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
                }

                function handleSync(buttonId, action, successMsg, updateCallback) {
                    const $btn = $('#' + buttonId);
                    if (!$btn.data('original-text')) {
                        $btn.data('original-text', $btn.text());
                    }

                    $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 8px 0 0"></span> Sincronizando...');

                    $.post(ajaxurl, {
                        action: action,
                        nonce: sapwc_ajax.nonce
                    }).done(function(res) {
                        if (res.success) {
                            showToast(successMsg);
                            if (res.data.last_sync && updateCallback) updateCallback(res.data);
                        } else {
                            showToast(res.data.message || '❌ Error desconocido', 'error');
                        }
                    }).fail(function() {
                        showToast('❌ Error de red', 'error');
                    }).always(function() {
                        $btn.prop('disabled', false).text($btn.data('original-text'));
                    });
                }


                $('.sapwc-sync-button').each(function() {
                    const $btn = $(this);
                    $btn.data('original-text', $btn.text());
                });

                $('#sapwc-sync-orders').on('click', function(e) {
                    e.preventDefault();
                    handleSync('sapwc-sync-orders', 'sapwc_send_orders', '✅ Pedidos sincronizados correctamente', function(data) {
                        $('#sapwc-last-sync').text(data.last_sync);
                        $('#sapwc-last-docentry').text(data.last_docentry);
                    });
                });

                $('#sapwc-sync-existing').on('click', function(e) {
                    e.preventDefault();
                    handleSync('sapwc-sync-existing', 'sapwc_sync_existing_products', '✅ Productos existentes sincronizados.', function(data) {
                        $('#sapwc-last-stock-sync').text(data.last_sync);
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

add_action('wp_ajax_sapwc_sync_stock_now', function () {
    nocache_headers(); //evitar cache
    sapwc_sync_stock_items();
});
add_action('wp_ajax_sapwc_sync_existing_products', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');
    nocache_headers();
    sapwc_sync_existing_products();
});


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

    register_setting('sapwc_sync_settings', 'sapwc_warehouse_tariff_map');

    register_setting('sapwc_sync_settings', 'sapwc_use_price_after_vat');

    register_setting('sapwc_sync_settings', 'sapwc_customer_filter_type');
    register_setting('sapwc_sync_settings', 'sapwc_customer_filter_value');
});

function sapwc_cron_sync_stock_callback()
{
    if (get_option('sapwc_sync_stock_auto') !== '1') return;

    error_log('[SAPWC Cron Stock] Iniciando sincronización automática de stock.');
    sapwc_sync_existing_products(); // tu función ya hace todo lo necesario
}
add_action('sapwc_cron_sync_stock', 'sapwc_cron_sync_stock_callback');



add_filter('cron_schedules', function ($schedules) {
    $schedules['twicedaily'] = ['interval' => 43200, 'display' => 'Cada 12 horas'];
    return $schedules;
});
add_action('wp_ajax_sapwc_force_cron', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');
    do_action('sapwc_cron_sync_orders');
    wp_send_json_success();
});
add_action('wp_ajax_sapwc_force_stock_cron', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');
    do_action('sapwc_cron_sync_stock');
    wp_send_json_success();
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
    $mode = get_option('sapwc_mode', 'ecommerce');

    if ($mode === 'b2b') {
        sapwc_sync_stock_items_b2b();
        return;
    }

    // ecommerce
    sapwc_sync_stock_items_ecommerce();
}

function sapwc_sync_stock_items_ecommerce()
{
    $mode = get_option('sapwc_mode', 'ecommerce');
    if ($mode === 'b2b') {
        sapwc_sync_stock_items_b2b();
        return;
    }
    // sigue con modo ecommerce...
    $is_ajax = defined('DOING_AJAX') && DOING_AJAX;

    $log_error = function ($msg) use ($is_ajax) {
        error_log('[SAPWC Sync Stock] ' . $msg);
        if ($is_ajax) {
            SAPWC_Logger::log(0, 'stock', 'error', $msg);
            wp_send_json_error(['message' => '❌ ' . $msg]);
        }
    };

    if (get_option('sapwc_sync_stock_auto') !== '1' && !$is_ajax) return;

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        $log_error('No hay conexión activa con SAP.');
        return;
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    if (!$login['success']) {
        $log_error('Login fallido: ' . $login['message']);
        return;
    }

    $default_tariff = get_option('sapwc_selected_tariff');
    $almacenes = get_option('sapwc_selected_warehouses', ['01']);
    $warehouse_tariffs = get_option('sapwc_warehouse_tariff_map', []);
    if (!is_array($warehouse_tariffs)) $warehouse_tariffs = [];

    if (!$default_tariff || empty($almacenes)) {
        $log_error('Faltan datos clave. <a href="' . admin_url('options-general.php?page=sapwc-sync-settings') . '" target="_blank">Configura la tarifa base y almacenes aquí</a>.');
        return;
    }

    $all_tariffs = $client->get_price_lists();
    $valid_tariff_ids = array_column($all_tariffs, 'id');

    if (!in_array($default_tariff, $valid_tariff_ids)) {
        $log_error("Tarifa base inválida: $default_tariff");
        return;
    }

    foreach ($warehouse_tariffs as $wh => $tariff) {
        if (empty($tariff)) {
            // Tarifa vacía: se usará la tarifa base, así que no hacer nada
            unset($warehouse_tariffs[$wh]);
            continue;
        }
        if (!in_array($tariff, $valid_tariff_ids)) {
            error_log("[SAPWC] Tarifa inválida para almacén $wh: $tariff");
            unset($warehouse_tariffs[$wh]);
        }
    }

    $response = $client->get("/Items?\$select=ItemCode,ItemPrices,ItemWarehouseInfoCollection");
    if (!isset($response['value'])) {
        $log_error("No se pudo obtener el listado de ítems desde SAP.");
        return;
    }

    $prices_include_tax = get_option('woocommerce_prices_include_tax') === 'yes';

    foreach ($response['value'] as $item) {
        $sku = $item['ItemCode'];
        error_log("[SAPWC] Procesando producto: " . $item['ItemCode']);

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            error_log("[SAPWC] Producto no encontrado en WooCommerce por SKU: $sku");
            continue;
        }

        $product = wc_get_product($product_id);
        if (!$product) continue;

        $product_warehouse = get_post_meta($product_id, 'almacen', true);
        $applicable_tariff = $default_tariff;

        if (!empty($product_warehouse) && isset($warehouse_tariffs[$product_warehouse])) {
            $applicable_tariff = $warehouse_tariffs[$product_warehouse];
        }

        $precio = null;
        foreach ($item['ItemPrices'] as $price) {
            if ((string)$price['PriceList'] === (string)$applicable_tariff) {
                $precio = (float)$price['Price'];
                break;
            }
        }

        $stock_total = 0;
        foreach ($item['ItemWarehouseInfoCollection'] as $wh) {
            if (in_array($wh['WarehouseCode'], $almacenes)) {
                $stock_total += (float)$wh['InStock'];
            }
        }

        $price_final = $precio;
        if ($prices_include_tax && $precio !== null) {
            $tax_class = $product->get_tax_class();
            $tax_rates = WC_Tax::get_rates($tax_class);
            if (!empty($tax_rates)) {
                $rate = reset($tax_rates);
                if (isset($rate['rate'])) {
                    $price_final = round($precio * (1 + ((float)$rate['rate'] / 100)), 2);
                }
            }
        }

        update_post_meta($product_id, '_regular_price', $price_final);
        update_post_meta($product_id, '_price', $price_final);
        update_post_meta($product_id, '_sapwc_price_net', $precio);
        update_post_meta($product_id, '_sapwc_stock_total', $stock_total);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock_total);
        $product->set_stock_status($stock_total > 0 ? 'instock' : 'outofstock');
        $product->save();
    }

    update_option('sapwc_stock_last_sync', current_time('mysql'));
    $context = $is_ajax ? 'manual' : 'cron';
    SAPWC_Logger::log(0, 'stock_' . $context, 'success', 'Stock actualizado desde ' . strtoupper($context));



    if ($is_ajax) {
        wp_send_json_success([
            'message'   => '✅ Stock actualizado con éxito.',
            'last_sync' => current_time('mysql')
        ]);
    }
}
function sapwc_sync_stock_items_b2b()
{
    $is_ajax = defined('DOING_AJAX') && DOING_AJAX;
    $log_error = function ($msg) use ($is_ajax) {
        error_log('[SAPWC Sync B2B] ' . $msg);
        if ($is_ajax) {
            SAPWC_Logger::log(0, 'stock_b2b', 'error', $msg);
            wp_send_json_error(['message' => '❌ ' . $msg]);
        }
    };
    // Validación de campos obligatorios antes de empezar
    $args = [
        'post_type'   => ['product', 'product_variation'],
        'post_status' => 'publish',
        'numberposts' => -1,
    ];

    $productos = get_posts($args);
    $errores = 0;

    foreach ($productos as $post) {
        $id = $post->ID;
        $sku = get_post_meta($id, '_sku', true);
        $almacen = get_post_meta($id, 'almacen', true);
        if (!$sku || !$almacen) {
            $errores++;
            error_log("[SAPWC B2B Check] Producto $id no cumple requisitos: SKU o almacén faltante.");
        }
    }

    if ($errores > 0) {
        return $log_error("❌ Hay $errores productos sin SKU o almacén. Corrige esto antes de sincronizar.");
    }


    $conn = sapwc_get_active_connection();
    if (!$conn) return $log_error('No hay conexión activa con SAP.');

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    if (!$login['success']) return $log_error('Login fallido: ' . $login['message']);

    $default_tariff = get_option('sapwc_selected_tariff');
    $almacenes = get_option('sapwc_selected_warehouses', ['01']);
    $warehouse_tariffs = get_option('sapwc_warehouse_tariff_map', []);
    if (!is_array($warehouse_tariffs)) $warehouse_tariffs = [];

    $use_price_after_vat = get_option('sapwc_use_price_after_vat', '0') === '1';

    $response = $client->get("/Items?\$select=ItemCode,ItemPrices,ItemWarehouseInfoCollection");
    if (!isset($response['value'])) {
        return $log_error("No se pudo obtener el listado de ítems desde SAP.");
    }

    foreach ($response['value'] as $item) {
        $sku = $item['ItemCode'];
        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) continue;

        $product = wc_get_product($product_id);
        if (!$product) continue;

        $product_warehouse = get_post_meta($product_id, 'almacen', true);
        $applicable_tariff = $default_tariff;
        if (!empty($product_warehouse) && isset($warehouse_tariffs[$product_warehouse])) {
            $applicable_tariff = $warehouse_tariffs[$product_warehouse];
        }

        $precio = null;
        $precio_vat = null;
        foreach ($item['ItemPrices'] ?? [] as $price) {
            if ((string)$price['PriceList'] === (string)$applicable_tariff) {
                $precio = (float)$price['Price'];
                $precio_vat = isset($price['PriceAfterVAT']) ? (float)$price['PriceAfterVAT'] : null;
                break;
            }
        }

        $stock_total = 0;
        foreach ($item['ItemWarehouseInfoCollection'] ?? [] as $wh) {
            if (in_array($wh['WarehouseCode'], $almacenes)) {
                $stock_total += (float)$wh['InStock'];
            }
        }

        $price_final = $use_price_after_vat && $precio_vat !== null ? $precio_vat : $precio;

        // Guardar precios y stock como siempre
        update_post_meta($product_id, '_regular_price', $price_final);
        update_post_meta($product_id, '_price', $price_final);
        update_post_meta($product_id, '_sapwc_price_net', $precio);
        update_post_meta($product_id, '_sapwc_stock_total', $stock_total);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock_total);
        $product->set_stock_status($stock_total > 0 ? 'instock' : 'outofstock');
        $product->save();

        // Guardar campos extra
        update_post_meta($product_id, 'pvp', $precio); // Precio base
        update_post_meta($product_id, 'porcentaje_de_descuento', ''); // Lo dejamos vacío por ahora
        update_post_meta($product_id, 'pvt_caja', ''); // Si quieres usarlo más adelante
        update_post_meta($product_id, 'compra_minima', ''); // Si se recupera luego desde SAP
    }

    update_option('sapwc_stock_last_sync', current_time('mysql'));
    SAPWC_Logger::log(0, 'stock_b2b', 'success', 'Stock actualizado en modo B2B');

    if ($is_ajax) {
        wp_send_json_success([
            'message' => '✅ Stock B2B actualizado con éxito.',
            'last_sync' => current_time('mysql')
        ]);
    }
}



// Mostrar en la UI
add_action('sapwc_render_price_settings_section', function () {
    $use_price_after_vat = get_option('sapwc_use_price_after_vat', '0');
    ?>
    <tr>
        <th scope="row">Precio desde SAP</th>
        <td>
            <label><input type="checkbox" name="sapwc_use_price_after_vat" value="1" <?php checked($use_price_after_vat, '1'); ?>> Usar <code>PriceAfterVAT</code> si está disponible</label>
            <p class="description">Si activas esta opción, el plugin usará <code>PriceAfterVAT</code> en lugar de <code>Price</code> si existe, ideal si WooCommerce está configurado con precios con IVA incluido.</p>
        </td>
    </tr>
<?php
});



// Modificar función de sincronización de productos existentes
function sapwc_sync_existing_products()
{
    $is_ajax = defined('DOING_AJAX') && DOING_AJAX;
    $log_error = function ($msg) use ($is_ajax) {
        error_log('[SAPWC Sync Existing] ' . $msg);
        if ($is_ajax) {
            SAPWC_Logger::log(0, 'existing_manual', 'error', $msg);
            wp_send_json_error(['message' => '❌ ' . $msg]);
        }
    };

    $conn = sapwc_get_active_connection();
    if (!$conn) return $log_error('No hay conexión activa con SAP.');

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    if (!$login['success']) return $log_error('Login fallido: ' . $login['message']);

    $default_tariff = get_option('sapwc_selected_tariff');
    $almacenes = get_option('sapwc_selected_warehouses', ['01']);
    $warehouse_tariffs = get_option('sapwc_warehouse_tariff_map', []);
    if (!is_array($warehouse_tariffs)) $warehouse_tariffs = [];

    $use_price_after_vat = get_option('sapwc_use_price_after_vat', '0') === '1';

    $args = [
        'post_type'   => ['product', 'product_variation'],
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_query'  => [
            [
                'key'     => '_sku',
                'compare' => 'EXISTS'
            ]
        ]
    ];

    $products = get_posts($args);
    $success = 0;
    $fail = 0;

    foreach ($products as $post) {
        $product_id = $post->ID;
        $sku = get_post_meta($product_id, '_sku', true);
        if (!$sku) continue;

        $item = $client->get("/Items('$sku')");
        if (!isset($item['ItemCode'])) {
            error_log("[SAPWC] SKU no encontrado en SAP: $sku");
            $fail++;
            continue;
        }

        $product = wc_get_product($product_id);
        if (!$product) continue;

        $product_warehouse = get_post_meta($product_id, 'almacen', true);
        $applicable_tariff = $default_tariff;
        if (!empty($product_warehouse) && isset($warehouse_tariffs[$product_warehouse])) {
            $applicable_tariff = $warehouse_tariffs[$product_warehouse];
        }

        $precio = null;
        $precio_vat = null;
        foreach ($item['ItemPrices'] ?? [] as $price) {
            if ((string)$price['PriceList'] === (string)$applicable_tariff) {
                $precio = (float)$price['Price'];
                $precio_vat = isset($price['PriceAfterVAT']) ? (float)$price['PriceAfterVAT'] : null;
                break;
            }
        }

        $price_final = $use_price_after_vat && $precio_vat !== null ? $precio_vat : $precio;

        $stock_total = 0;
        foreach ($item['ItemWarehouseInfoCollection'] ?? [] as $wh) {
            if (in_array($wh['WarehouseCode'], $almacenes)) {
                $stock_total += (float)$wh['InStock'];
            }
        }

        update_post_meta($product_id, '_regular_price', $price_final);
        update_post_meta($product_id, '_price', $price_final);
        update_post_meta($product_id, '_sapwc_price_net', $precio);
        update_post_meta($product_id, '_sapwc_stock_total', $stock_total);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock_total);
        $product->set_stock_status($stock_total > 0 ? 'instock' : 'outofstock');
        $product->save();
        $success++;
    }

    update_option('sapwc_stock_last_sync', current_time('mysql'));
    SAPWC_Logger::log(0, 'existing_manual', 'success', "Sincronizados $success productos existentes con éxito (fallos: $fail)");

    if ($is_ajax) {
        wp_send_json_success([
            'message'   => "✅ Se sincronizaron $success productos existentes.",
            'last_sync' => current_time('mysql')
        ]);
    }
}


if (!wp_next_scheduled('sapwc_cron_sync_stock')) {
    wp_schedule_event(time() + 120, 'hourly', 'sapwc_cron_sync_stock');
}
