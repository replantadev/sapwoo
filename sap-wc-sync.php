<?php
/*
Plugin Name: SAP Woo Sync
Plugin URI: https://replanta.es
Description: Sincroniza pedidos de WooCommerce con SAP Business One.
Version: 1.2.6
Author: Replanta Dev
Author URI: https://replanta.es
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: sapwoo
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}

// Definir constantes
define('SAPWC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SAPWC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Cargar archivos necesarios
add_action('plugins_loaded', 'sapwc_load_dependencies');
function sapwc_load_dependencies()
{
    require_once SAPWC_PLUGIN_PATH . 'admin/class-settings-page.php';
    require_once SAPWC_PLUGIN_PATH . 'admin/class-sync-options-page.php';
    require_once SAPWC_PLUGIN_PATH . 'admin/class-failed-orders-page.php';
    require_once SAPWC_PLUGIN_PATH . 'admin/class-logs-page.php';
    require_once SAPWC_PLUGIN_PATH . 'admin/class-orders-page.php';
    require_once SAPWC_PLUGIN_PATH . 'admin/class-mapping-page.php';
    require_once SAPWC_PLUGIN_PATH . 'admin/class-sap-orders-table.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/class-api-client.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/class-sap-sync.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/class-logger.php';
}

// Actualizaciones automáticas desde GitHub
if (file_exists(SAPWC_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once SAPWC_PLUGIN_PATH . 'vendor/autoload.php';
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/replantadev/sapwoo/',
    __FILE__,
    'sapwoo'
);

// Si el repositorio es privado, agrega autenticación de forma segura
if (defined('SAPWC_GITHUB_TOKEN')) {
    $updateChecker->setAuthentication(SAPWC_GITHUB_TOKEN);
}

// Configurar la rama principal
$updateChecker->setBranch('main');

// Verificar si WooCommerce está activo
add_action('admin_init', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('SAP Woo Sync', 'sapwoo') . '</strong> ' . esc_html__('requiere que', 'sapwoo') . ' <strong>' . esc_html__('WooCommerce', 'sapwoo') . '</strong> ' . esc_html__('esté instalado y activo.', 'sapwoo') . '</p></div>';
        });

        // Desactivar el plugin si WooCommerce no está activo
        deactivate_plugins(plugin_basename(__FILE__));
    }
});

// Crear tabla de logs al activar el plugin
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

// Configuración inicial al activar el plugin
register_activation_hook(__FILE__, function () {
    if (get_option('sapwc_connection_index') === false) {
        update_option('sapwc_connection_index', 0);
    }
});

// Menú de administración
add_action('admin_menu', function () {
    $capability = 'edit_others_shop_orders';

    // Menú principal
    add_menu_page(
        __('SAP Woo Sync', 'sapwoo'),
        __('SAP Woo', 'sapwoo'),
        $capability,
        'sapwc-settings',
        null,
        'dashicons-update',
        56
    );

    // Submenús compartidos
    add_submenu_page('sapwc-settings', __('Pedidos Woo', 'sapwoo'), __('Pedidos Woo', 'sapwoo'), $capability, 'sapwc-orders', ['SAPWC_Orders_Page', 'render']);
    add_submenu_page('sapwc-settings', __('Mapeo de Campos', 'sapwoo'), __('Mapeo de Campos', 'sapwoo'), $capability, 'sapwc-mapping', ['SAPWC_Mapping_Page', 'render']);
    add_submenu_page('sapwc-settings', __('Sincronización', 'sapwoo'), __('Sincronización', 'sapwoo'), $capability, 'sapwc-sync-options', ['SAPWC_Sync_Options_Page', 'render']);
    add_submenu_page('sapwc-settings', __('Pedidos Fallidos', 'sapwoo'), __('Pedidos Fallidos', 'sapwoo'), $capability, 'sapwc-failed-orders', ['SAPWC_Failed_Orders_Page', 'render']);

    // Solo admins: Credenciales y Logs
    if (current_user_can('manage_options')) {
        add_submenu_page('sapwc-settings', __('Credenciales SAP', 'sapwoo'), __('Credenciales SAP', 'sapwoo'), 'manage_options', 'sapwc-settings', ['SAPWC_Settings_Page', 'render']);
        add_submenu_page('sapwc-settings', __('Logs', 'sapwoo'), __('Logs', 'sapwoo'), 'manage_options', 'sapwc-logs', ['SAPWC_Logs_Page', 'render']);
    }
});


add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'sapwc') !== false) {
        wp_enqueue_script('sapwc-admin', SAPWC_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], time(), true);
        wp_localize_script('sapwc-admin', 'sapwc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sapwc_nonce')
        ]);
        wp_enqueue_style('sapwc-admin-style', SAPWC_PLUGIN_URL . 'assets/css/sapwc-admin.css');
        wp_enqueue_style('sapwc-toggle-style', SAPWC_PLUGIN_URL . 'assets/css/sapwc-toggle.css');
    }
});



// AJAX para test de conexión

add_action('wp_ajax_sapwc_test_connection', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => __('❌ No hay conexión activa configurada.', 'sapwoo')]);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if ($login['success']) {
        wp_send_json_success(__('✅ Conexión correcta con SAP.', 'sapwoo'));
    } else {
        wp_send_json_error(['message' => __('❌ Error de SAP: ', 'sapwoo') . $login['message']]);
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

            echo '<span style="color:green;font-weight:bold;">' . esc_html__('✔ Enviado', 'sapwoo') . '</span>';

            if ($docentry) {

                echo '<br><small>ID: ' . esc_html($docentry) . '</small>';
            }
        } else {

            echo '<span style="color:#999;">' . esc_html__('–', 'sapwoo') . '</span>';
        }
    }
}, 10, 2);



add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();


    $is_sap_page = strpos($hook, 'sapwc') !== false || strpos($screen->id, 'sapwc') !== false;
    wp_enqueue_style('dashicons');

    wp_enqueue_script('underscore');
    // Cargar siempre que estemos en el admin
    wp_enqueue_script('sapwc-admin', SAPWC_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], time(), true);
    wp_localize_script('sapwc-admin', 'sapwc_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sapwc_nonce')
    ]);

    if ($is_sap_page) {
        wp_enqueue_style('sapwc-admin-style', SAPWC_PLUGIN_URL . 'assets/css/sapwc-admin.css');
        wp_enqueue_style('sapwc-toggle-style', SAPWC_PLUGIN_URL . 'assets/css/sapwc-toggle.css');
        wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], null, true);
        wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
    }
});






add_action('sapwc_cron_sync_orders', 'sapwc_cron_sync_orders_callback');

function sapwc_cron_sync_orders_callback()
{
    if (get_option('sapwc_sync_orders_auto') !== '1') return;

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        error_log(__('❌ No hay conexión activa configurada.', 'sapwoo'));
        return;
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    if (!$login['success']) {
        error_log(__('❌ Error de conexión: ', 'sapwoo') . $login['message']);
        return;
    }

    $sync = new SAPWC_Sync_Handler($client);
    $orders = wc_get_orders([
        'status' => ['processing'],
        'limit' => -1
    ]);

    $sent = 0;
    $skipped = 0;
    $errors = 0;
    $last_docentry = null;

    foreach ($orders as $order) {
        $result = $sync->send_order($order);

        if (!empty($result['skipped'])) {
            $skipped++;
        } elseif (!empty($result['success'])) {
            $sent++;
            if (!empty($result['docentry'])) {
                $last_docentry = $result['docentry'];
            }
        } else {
            $errors++;
            SAPWC_Logger::log($order->get_id(), 'cron', 'error', 'Falló sincronización desde cron: ' . ($result['message'] ?? 'Error desconocido'));
        }
    }

    update_option('sapwc_orders_last_sync', current_time('mysql'));
    if ($last_docentry) {
        update_option('sapwc_orders_last_docentry', $last_docentry);
    }

    error_log(sprintf(__('Enviados: %d | Ya enviados: %d | Errores: %d', 'sapwoo'), $sent, $skipped, $errors));
    SAPWC_Logger::log(null, 'cron', 'info', "Finalizó sync automática. Enviados: $sent | Saltados: $skipped | Errores: $errors");
}





// Añadir estado en la admin bar
// Mostrar mini widget SAP en admin bar
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('edit_others_shop_orders')) return;

    $sync_orders = get_option('sapwc_sync_orders_auto') === '1';
    $sync_stock  = get_option('sapwc_sync_stock_auto') === '1';
    $last_sync   = get_option('sapwc_orders_last_sync', 'Nunca');

    // Prueba rápida de conexión (opcionalmente podrías cachear)
    $conn = sapwc_get_active_connection();
    if (!$conn) {
        $status_icon = '<span class="dashicons dashicons-no-alt" style="font-family: dashicons;color:red"></span>';
        $status_msg  = __('No configurada', 'sapwoo');
    } else {
        $client = new SAPWC_API_Client($conn['url']);
        $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

        $status_icon = $login['success'] ? '<span class="dashicons dashicons-yes" style="font-family: dashicons;color:green"></span>' : '<span class="dashicons dashicons-no-alt" style="font-family: dashicons;color:red"></span>';
        $status_msg = $login['success'] ? __('Conectado a SAP', 'sapwoo') : __('Error de conexión', 'sapwoo');
    }

    $title = '<span class="dashicons dashicons-update" style="font-family: dashicons; margin: 0 5px;"></span>';
    $wp_admin_bar->add_node([
        'id'    => 'sapwc_status',
        'title' => " $title SAP Woo $status_icon",
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
/**
 * Obtiene la conexión activa de SAP desde las opciones del plugin.
 *
 * @return array|null La conexión activa o null si no está configurada.
 */
function sapwc_get_active_connection()
{
    $all_connections = get_option('sapwc_connections', []);
    $index = get_option('sapwc_connection_index', 0);

    if (!isset($all_connections[$index])) {
        error_log(__('❌ No hay conexión activa configurada en SAP Woo Sync.', 'sapwoo'));
        return null;
    }

    $connection = $all_connections[$index];

    // Asegurar que el campo 'ssl' esté seteado (por compatibilidad)
    if (!isset($connection['ssl'])) {
        $connection['ssl'] = false;
    }

    if (empty($connection['url']) || empty($connection['user']) || empty($connection['pass']) || empty($connection['db'])) {
        error_log(__('❌ Conexión activa incompleta: ', 'sapwoo') . print_r($connection, true));
        return null;
    }

    return $connection;
}
function sapwc_build_orders_query()
{
    $mode = get_option('sapwc_mode', 'ecommerce');
    $query_info = [
        'mode' => $mode,
        'query' => '',
        'params' => [],
        'filter_after_php' => false,
    ];

    if ($mode === 'ecommerce') {
        $peninsula = sanitize_text_field(get_option('sapwc_cardcode_peninsula', 'WNAD PENINSULA'));
        $canarias  = sanitize_text_field(get_option('sapwc_cardcode_canarias', 'WNAD CANARIAS'));

        $query_info['params'] = compact('peninsula', 'canarias');
        $query_info['query'] = "/Orders?\$filter=(CardCode eq '$peninsula' or CardCode eq '$canarias')&\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
    } elseif ($mode === 'b2b') {
        $filter_type  = get_option('sapwc_customer_filter_type', 'starts');
        $filter_value = sanitize_text_field(trim(get_option('sapwc_customer_filter_value', '')));

        $query_info['params'] = compact('filter_type', 'filter_value');

        if (!empty($filter_value)) {
            if ($filter_type === 'starts' || $filter_type === 'prefix_numbers') {
                // En ambos casos usamos startswith en SAP
                $query_info['query'] = "/Orders?\$filter=startswith(CardCode,'$filter_value')&\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";

                // En prefix_numbers, luego filtramos en PHP
                if ($filter_type === 'prefix_numbers') {
                    $query_info['filter_after_php'] = true;
                }
            } elseif ($filter_type === 'contains') {
                $query_info['query'] = "/Orders?\$filter=contains(CardCode,'$filter_value')&\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
            }
        } else {
            $query_info['query'] = "/Orders?\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
        }
    } else {
        $query_info['query'] = "/Orders?\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
    }

    return $query_info;
}



// AJAX para enviar pedidos a SAP
add_action('wp_ajax_sapwc_send_orders', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(__('❌ No hay conexión activa con SAP.', 'sapwoo'));
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    if (!$login['success']) {
        wp_send_json_error(__('❌ Error al conectar con SAP: ', 'sapwoo') . $login['message']);
    }

    $sync = new SAPWC_Sync_Handler($client);
    $orders = wc_get_orders([
        'status' => ['processing'], // puedes añadir más estados si quieres
        'limit'  => -1
    ]);

    $last_docentry = null;
    foreach ($orders as $order) {
        if (!$order->get_meta('_sap_exported')) {
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

    wp_send_json_success([
        'message' => __('Pedidos sincronizados correctamente.', 'sapwoo'),
        'last_sync' => current_time('mysql'),
        'last_docentry' => $last_docentry
    ]);
});


//mostrar solo si hay stock o agotado por producto, no las unidades en frontend:
add_filter('woocommerce_get_availability_text', 'custom_availability_text', 10, 2);
function custom_availability_text($availability, $product)
{
    if ($product->is_in_stock()) {
        return __('En stock', 'woocommerce');
    } else {
        return __('Agotado', 'woocommerce');
    }
}
