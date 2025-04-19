<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'sapwc_orders',
        'Configuración SAP WC',
        'Configuración',
        'manage_options',
        'sapwc_settings',
        'sapwc_render_settings_page'
    );
});

function sapwc_render_settings_page()
{
    $sync_orders_auto = get_option('sapwc_sync_orders_auto', 'manual');
    $sync_stock_auto = get_option('sapwc_sync_stock_auto', 'manual');
    $last_sync = get_option('sapwc_orders_last_sync');
    $last_sync_txt = $last_sync ? date('Y-m-d H:i:s', strtotime($last_sync)) : 'Nunca';
    if (isset($_POST['sapwc_select_connection'])) {
        update_option('sapwc_connection_index', intval($_POST['sapwc_connection_index']));
        echo '<div class="updated"><p>Conexión activa cambiada.</p></div>';
    }
    
?>
    <div class="wrap">
        <h1>Configuración de Sincronización SAP WC</h1>

        <style>
            .sapwc-box {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 30px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            }

            .sapwc-switch {
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
            }

            .sapwc-label {
                margin-bottom: 6px;
                font-size: 14px;
                font-weight: 600;
            }
        </style>

        <form method="post" action="options.php">
            <?php settings_fields('sapwc_settings_group'); ?>
            <div class="sapwc-box">
                <h2>📅 Sincronización de pedidos</h2>
                <div class="sapwc-switch">
                    <label class="sapwc-label">Modo:</label>
                    <select name="sapwc_sync_orders_auto">
                        <option value="manual" <?php selected($sync_orders_auto, 'manual'); ?>>Manual</option>
                        <option value="auto" <?php selected($sync_orders_auto, 'auto'); ?>>Automático</option>
                    </select>
                </div>
                <p>Ultima sincronización: <strong><?php echo esc_html($last_sync_txt); ?></strong></p>
                <p>Pedido más reciente en SAP (DocEntry): <code id="sapwc-last-docentry">Cargando...</code></p>
            </div>

            <div class="sapwc-box">
                <h2>📊 Sincronización de stock y precios</h2>
                <div class="sapwc-switch">
                    <label class="sapwc-label">Modo:</label>
                    <select name="sapwc_sync_stock_auto">
                        <option value="manual" <?php selected($sync_stock_auto, 'manual'); ?>>Manual</option>
                        <option value="auto" <?php selected($sync_stock_auto, 'auto'); ?>>Automático</option>
                    </select>
                </div>
                <p>Almacenes a sincronizar (próximamente): <strong>01</strong> y <strong>LI</strong></p>
                <p>Tarifa base a usar (próximamente): <strong>Seleccionar</strong></p>
            </div>

            <?php submit_button('Guardar configuración'); ?>
        </form>
        <?php
        $current_index = get_option('sapwc_connection_index', 0);
        $connections = get_option('sapwc_connections', []);

        echo '<h2>🌐 Conexiones SAP disponibles</h2>';
        if (!empty($connections)) {
            echo '<form method="post">';
            foreach ($connections as $index => $conn) {
                $selected = ($index == $current_index) ? 'checked' : '';
                echo "<label><input type='radio' name='sapwc_connection_index' value='{$index}' $selected> {$conn['name']} ({$conn['db']})</label><br>";
            }
            submit_button('Usar esta conexión', 'secondary', 'sapwc_select_connection');
            echo '</form>';
        }

        ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=sapwc_last_sap_docentry')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('sapwc-last-docentry').textContent = data.docentry;
                    } else {
                        document.getElementById('sapwc-last-docentry').textContent = 'No disponible';
                    }
                });
        });
    </script>
<?php
}

add_action('admin_init', function () {
    register_setting('sapwc_settings_group', 'sapwc_sync_orders_auto');
    register_setting('sapwc_settings_group', 'sapwc_sync_stock_auto');
});

add_action('wp_ajax_sapwc_last_sap_docentry', function () {
    global $wpdb;
    $doc = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sap_docentry' ORDER BY post_id DESC LIMIT 1");
    if ($doc) {
        wp_send_json_success(['docentry' => $doc]);
    } else {
        wp_send_json_error();
    }
});
