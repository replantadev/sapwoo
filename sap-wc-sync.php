<?php

/*

Plugin Name: SAP Woo Sync

Description: Sincroniza pedidos de WooCommerce con SAP Business One.

Version: 1.0.0
Author: Replanta Dev


*/



if (!defined('ABSPATH')) exit;



// Cargar archivos necesarios

define('SAPWC_PATH', plugin_dir_path(__FILE__));

define('SAPWC_URL', plugin_dir_url(__FILE__));



require_once SAPWC_PATH . 'admin/class-settings-page.php';
require_once SAPWC_PATH . 'admin/class-sync-options-page.php';
require_once SAPWC_PATH . 'admin/class-failed-orders-page.php';
require_once SAPWC_PATH . 'admin/class-logs-page.php';


require_once SAPWC_PATH . 'admin/class-orders-page.php';

require_once SAPWC_PATH . 'admin/class-mapping-page.php';

require_once SAPWC_PATH . 'admin/class-sap-orders-table.php';

require_once SAPWC_PATH . 'includes/class-api-client.php';

require_once SAPWC_PATH . 'includes/class-sap-sync.php';
require_once SAPWC_PATH . 'includes/class-logger.php';


// Actualizaciones automáticas desde GitHub
require_once SAPWC_PATH . 'plugin-update-checker/plugin-update-checker.php';



$updateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/replantadev/sapwoo/',
    __FILE__,
    'sapwoo'
);

// Cuando lo hagamos privado
// $updateChecker->setAuthentication('GITHUB_TOKEN_AQUI');

$updateChecker->setBranch('main');

// Verificar si WooCommerce está activo
add_action('admin_init', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>SAP Woo Sync</strong> requiere que <strong>WooCommerce</strong> esté instalado y activo.</p></div>';
        });

        // Desactivar el plugin si WooCommerce no está activo
        deactivate_plugins(plugin_basename(__FILE__));
    }
});








register_activation_hook(__FILE__, 'sapwc_create_log_table');

function sapwc_create_log_table()
{
    global $wpdb;
    $table = $wpdb->prefix . 'sapwc_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT,
        action VARCHAR(100),
        status VARCHAR(20),
        message TEXT,
        docentry BIGINT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, function () {
    if (get_option('sapwc_connection_index') === false) {
        update_option('sapwc_connection_index', 0);
    }
});


add_action('admin_menu', function () {
    if (current_user_can('edit_others_shop_orders')) {
        add_menu_page('SAP Woo Sync', 'SAP Woo Sync', 'edit_others_shop_orders', 'sapwc-settings', null, 'dashicons-update');
        add_submenu_page('sapwc-settings', 'Credenciales SAP', 'Credenciales SAP', 'edit_others_shop_orders', 'sapwc-settings', ['SAPWC_Settings_Page', 'render']);
        add_submenu_page('sapwc-settings', 'Pedidos Woo', 'Pedidos Woo', 'edit_others_shop_orders', 'sapwc-orders', ['SAPWC_Orders_Page', 'render']);
        add_submenu_page('sapwc-settings', 'Mapeo de Campos', 'Mapeo de Campos', 'edit_others_shop_orders', 'sapwc-mapping', ['SAPWC_Mapping_Page', 'render']);
        add_submenu_page('sapwc-settings', 'Sincronización', 'Sincronización', 'edit_others_shop_orders', 'sapwc-sync-options', ['SAPWC_Sync_Options_Page', 'render']);
        add_submenu_page('sapwc-settings', 'Pedidos Fallidos', 'Pedidos Fallidos', 'edit_others_shop_orders', 'sapwc-failed-orders', ['SAPWC_Failed_Orders_Page', 'render']);
        add_submenu_page('sapwc-settings', 'Logs', 'Logs', 'manage_options', 'sapwc-logs', ['SAPWC_Logs_Page', 'render']);
    }
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'sapwc') !== false) {
        wp_enqueue_script('sapwc-admin', SAPWC_URL . 'assets/js/admin.js', ['jquery'], time(), true);
        wp_localize_script('sapwc-admin', 'sapwc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sapwc_nonce')
        ]);
        wp_enqueue_style('sapwc-admin-style', SAPWC_URL . 'assets/css/sapwc-admin.css');
        wp_enqueue_style('sapwc-toggle-style', SAPWC_URL . 'assets/css/sapwc-toggle.css');
    }
});



// AJAX para test de conexión

add_action('wp_ajax_sapwc_test_connection', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa configurada.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db']);

    if ($login['success']) {
        wp_send_json_success('✅ Conexión correcta con SAP.');
    } else {
        wp_send_json_error(['message' => '❌ Error de SAP: ' . $login['message']]);
    }
});




// 1. Añadir columna personalizada al listado de pedidos

add_filter('manage_edit-shop_order_columns', function ($columns) {

    $columns['sap_exported'] = 'SAP';

    return $columns;
});



// 2. Mostrar valor de la columna personalizada

add_action('manage_shop_order_posts_custom_column', function ($column, $post_id) {

    if ($column === 'sap_exported') {

        $exported = get_post_meta($post_id, '_sap_exported', true);

        $docentry = get_post_meta($post_id, '_sap_docentry', true);



        if ($exported) {

            echo '<span style="color:green;font-weight:bold;">✔ Enviado</span>';

            if ($docentry) {

                echo '<br><small>ID: ' . esc_html($docentry) . '</small>';
            }
        } else {

            echo '<span style="color:#999;">–</span>';
        }
    }
}, 10, 2);



add_action('admin_enqueue_scripts', function () {
    // Solo cargar si estamos en la página del plugin SAP WC
    $screen = get_current_screen();
    if (strpos($screen->id, 'toplevel_page_sapwc') !== false) {



        // CSS
        wp_enqueue_style('sapwc-admin-style', plugin_dir_url(__FILE__) . 'assets/css/sapwc-admin.css');
    }
});





// Cron para sincronizar pedidos automáticamente si está habilitado
add_action('sapwc_cron_sync_orders', function () {
    if (get_option('sapwc_sync_orders_auto') !== '1') return;

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db']);
    if (!$login['success']) return;

    $sync = new SAPWC_Sync_Handler($client);
    $orders = wc_get_orders(['status' => 'processing', 'limit' => -1]);
    $last_docentry = null;

    foreach ($orders as $order) {
        $already_sent = $order->get_meta('_sap_exported');
        if (!$already_sent) {
            $result = $sync->send_order($order);
            if ($result['success'] && isset($result['docentry'])) {
                $last_docentry = $result['docentry'];
            }
        }
    }

    update_option('sapwc_orders_last_sync', current_time('mysql'));
    if ($last_docentry) {
        update_option('sapwc_orders_last_docentry', $last_docentry);
    }
});



// Añadir estado en la admin bar
// Mostrar mini widget SAP en admin bar
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;

    $sync_orders = get_option('sapwc_sync_orders_auto') === '1';
    $sync_stock  = get_option('sapwc_sync_stock_auto') === '1';
    $last_sync   = get_option('sapwc_orders_last_sync', 'Nunca');

    // Prueba rápida de conexión (opcionalmente podrías cachear)
    $conn = sapwc_get_active_connection();
    if (!$conn) {
        $status_icon = '🔴';
        $status_msg  = 'No configurada';
    } else {
        $client = new SAPWC_API_Client($conn['url']);
        $login  = $client->login($conn['user'], $conn['pass'], $conn['db']);
        $status_icon = $login['success'] ? '🟢' : '🔴';
        $status_msg  = $login['success'] ? 'Conectado a SAP' : 'Error de conexión';
    }

    $wp_admin_bar->add_node([
        'id'    => 'sapwc_status',
        'title' => "🔄 SAP Woo $status_icon",
        'href'  => admin_url('admin.php?page=sapwc-settings'),
        'meta'  => ['title' => 'Estado SAP Woo Sync']
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'sapwc_status_connection',
        'parent' => 'sapwc_status',
        'title'  => "Conexión: <strong>$status_msg</strong>"
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'sapwc_status_orders',
        'parent' => 'sapwc_status',
        'title'  => "Pedidos: <strong>" . ($sync_orders ? 'Automática' : 'Manual') . "</strong>"
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'sapwc_status_stock',
        'parent' => 'sapwc_status',
        'title'  => "Stock: <strong>" . ($sync_stock ? 'Automática' : 'Manual') . "</strong>"
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'sapwc_status_lastsync',
        'parent' => 'sapwc_status',
        'title'  => "Últ. Sync Pedidos: <strong>$last_sync</strong>"
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'sapwc_status_action_sync',
        'parent' => 'sapwc_status',
        'title'  => '<span class="dashicons dashicons-update-alt" style="font-family: dashicons"></span> <span id="sapwc-sync-trigger">Forzar Sincronización</span>',
        'href'   => '#',
        'meta'   => ['onclick' => 'return false;']
    ]);
}, 100);


//Funcion de conexiones:


function sapwc_get_active_connection() {
    $all_connections = get_option('sapwc_connections', []);
    $index = get_option('sapwc_connection_index', 0);

    if (!isset($all_connections[$index])) {
        error_log('❌ No hay conexión activa configurada en SAP Woo Sync.');
        return null;
    }

    $connection = $all_connections[$index];

    if (empty($connection['url']) || empty($connection['user']) || empty($connection['pass']) || empty($connection['db'])) {
        error_log('❌ Conexión activa incompleta: ' . print_r($connection, true));
        return null;
    }

    return $connection;
}