<?php
/*
Plugin Name: SAP Woo Sync
Plugin URI: https://replanta.es
Description: Sincroniza pedidos de WooCommerce con SAP Business One.
Version: 1.5.0-beta
Author: Replanta Dev
Author URI: https://replanta.es
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: sapwoo
Domain Path: /languages
GitHub Plugin URI: https://github.com/replantadev/sapwoo
GitHub Branch: main
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
    require_once SAPWC_PLUGIN_PATH . 'admin/class-customers-import-page.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/class-api-client.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/class-sap-sync.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/class-logger.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/helper.php';
    
    // Clases para sincronización de clientes B2B
    require_once SAPWC_PLUGIN_PATH . 'includes/class-customer-sync.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/class-welcome-mailer.php';

    // Clases para importación de catálogo SAP → WooCommerce
    require_once SAPWC_PLUGIN_PATH . 'includes/class-category-sync.php';
    require_once SAPWC_PLUGIN_PATH . 'includes/class-product-sync.php';
    require_once SAPWC_PLUGIN_PATH . 'admin/class-import-page.php';
    require_once SAPWC_PLUGIN_PATH . 'admin/class-selective-import-page.php';
}

// Actualizaciones automáticas desde GitHub
if (file_exists(SAPWC_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once SAPWC_PLUGIN_PATH . 'vendor/autoload.php';
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/replantadev/sapwoo/',
    __FILE__,
    'sap-woo'
);
$updateChecker->setBranch('main');

// Autenticación con classic PAT (ghp_...) — compatible con Basic auth de PUC.
if (defined('SAPWC_GITHUB_TOKEN')) {
    $updateChecker->getVcsApi()->setAuthentication(SAPWC_GITHUB_TOKEN);
}

// Renombrar la carpeta del ZIP (sapwoo-vX.X.X) a sap-woo durante la actualización
add_filter('upgrader_source_selection', function($source, $remote_source, $upgrader, $hook_extra) {
    global $wp_filesystem;
    
    // Solo procesar si es nuestro plugin
    if (!isset($hook_extra['plugin']) || strpos($hook_extra['plugin'], 'sap-woo/') !== 0) {
        return $source;
    }
    
    // Si la carpeta no se llama sap-woo, renombrarla
    $corrected_source = trailingslashit($remote_source) . 'sap-woo/';
    if ($source !== $corrected_source && strpos(basename($source), 'sapwoo') !== false) {
        if ($wp_filesystem->move($source, $corrected_source)) {
            return $corrected_source;
        }
    }
    
    return $source;
}, 10, 4);

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
    add_submenu_page('sapwc-settings', __('Importación', 'sapwoo'), __('Importación', 'sapwoo'), $capability, 'sapwc-import', ['SAPWC_Import_Page', 'render']);
    add_submenu_page('sapwc-settings', __('Sincronización', 'sapwoo'), __('Sincronización', 'sapwoo'), $capability, 'sapwc-sync-options', ['SAPWC_Sync_Options_Page', 'render']);
    add_submenu_page('sapwc-settings', __('Mapeo de Campos', 'sapwoo'), __('Mapeo de Campos', 'sapwoo'), $capability, 'sapwc-mapping', ['SAPWC_Mapping_Page', 'render']);
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
        wp_send_json_error(['message' => __('No hay conexión activa configurada.', 'sapwoo')]);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if ($login['success']) {
        wp_send_json_success(__('Conexión correcta con SAP.', 'sapwoo'));
    } else {
        wp_send_json_error(['message' => __('Error de SAP: ', 'sapwoo') . $login['message']]);
    }
});

// AJAX para verificar campos UDF de clientes en SAP (debug en consola)
add_action('wp_ajax_sapwc_check_customer_udf', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sin permisos']);
    }

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => 'No hay conexión activa']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => 'Error de login: ' . $login['message']]);
    }

    $cardcode = isset($_POST['cardcode']) ? sanitize_text_field($_POST['cardcode']) : '';
    
    if ($cardcode) {
        // Consultar un cliente específico con todos sus campos UDF
        $response = $client->get("/BusinessPartners('{$cardcode}')");
    } else {
        // Consultar clientes web (U_ARTES_CLIW = 'S') - primeros 5
        $response = $client->get("/BusinessPartners?\$filter=U_ARTES_CLIW eq 'S'&\$top=5&\$select=CardCode,CardName,EmailAddress,U_ARTES_CLIW");
        
        // Si no encuentra con ese filtro, intentar ver la estructura de un cliente cualquiera
        if (!isset($response['value']) || empty($response['value'])) {
            $response = $client->get("/BusinessPartners?\$top=1");
            $response['_note'] = 'No se encontraron clientes con U_ARTES_CLIW=S. Mostrando estructura de ejemplo.';
        }
    }

    wp_send_json_success($response);
});

// AJAX para DEBUG: Ver mapeo de campos SAP → WooCommerce
add_action('wp_ajax_sapwc_debug_customer_mapping', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sin permisos']);
    }

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => 'No hay conexión activa']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => 'Error de login: ' . $login['message']]);
    }

    $cardcode = isset($_POST['cardcode']) ? sanitize_text_field($_POST['cardcode']) : '';
    $udf_field = get_option('sapwc_customer_udf_field', 'U_ARTES_CLIW');
    $udf_value = get_option('sapwc_customer_udf_value', 'S');

    // Si se proporciona un CardCode específico
    if ($cardcode) {
        $sap_data = $client->get("/BusinessPartners('{$cardcode}')");
        
        if (isset($sap_data['error'])) {
            wp_send_json_error(['message' => 'Cliente no encontrado: ' . ($sap_data['error']['message']['value'] ?? 'Error')]);
        }
        
        $customers = [$sap_data];
    } else {
        // Obtener primeros 3 clientes web para debug
        $response = $client->get("/BusinessPartners?\$filter={$udf_field} eq '{$udf_value}'&\$top=3");
        
        if (!isset($response['value'])) {
            // Intentar sin filtro para ver la estructura
            $response = $client->get("/BusinessPartners?\$top=1");
            if (isset($response['value'])) {
                $response['_debug_note'] = "No se encontraron clientes con {$udf_field}='{$udf_value}'. Mostrando cliente de ejemplo para ver estructura.";
            }
        }
        
        $customers = $response['value'] ?? [];
    }

    $results = [];
    
    foreach ($customers as $customer) {
        // Simular el mapeo tal como lo hace SAPWC_Customer_Sync
        $full_name = $customer['CardName'] ?? '';
        $first_name = '';
        $last_name = '';

        if (strpos($full_name, ',') !== false) {
            [$last_name, $first_name] = array_map('trim', explode(',', $full_name, 2));
        } else {
            $parts = explode(' ', $full_name, 2);
            $first_name = $parts[0] ?? '';
            $last_name = $parts[1] ?? '';
        }

        $display_name = !empty($customer['CardForeignName']) 
            ? $customer['CardForeignName'] 
            : trim("{$first_name} {$last_name}");

        $email = $customer['EmailAddress'] ?? '';
        if (empty($email)) {
            $email = strtolower($customer['CardCode'] ?? 'unknown') . '@cliente.temp';
        }

        // Buscar dirección de envío en BPAddresses
        $shipping = [];
        if (!empty($customer['BPAddresses']) && is_array($customer['BPAddresses'])) {
            foreach ($customer['BPAddresses'] as $addr) {
                if (($addr['AddressType'] ?? '') === 'bo_ShipTo') {
                    $shipping = [
                        'address_1' => $addr['Street'] ?? $addr['Address'] ?? '',
                        'city' => $addr['City'] ?? '',
                        'postcode' => $addr['ZipCode'] ?? '',
                        'state' => $addr['State'] ?? '',
                        'country' => $addr['Country'] ?? 'ES'
                    ];
                    break;
                }
            }
        }

        // Verificar si ya existe en WooCommerce
        $exists_in_woo = username_exists($customer['CardCode'] ?? '') ? true : false;
        if (!$exists_in_woo) {
            $users = get_users([
                'meta_key' => 'sapwc_cardcode',
                'meta_value' => $customer['CardCode'] ?? '',
                'number' => 1,
                'fields' => 'ID'
            ]);
            $exists_in_woo = !empty($users);
        }

        $results[] = [
            'sap_raw' => [
                'CardCode' => $customer['CardCode'] ?? null,
                'CardName' => $customer['CardName'] ?? null,
                'CardForeignName' => $customer['CardForeignName'] ?? null,
                'FederalTaxID' => $customer['FederalTaxID'] ?? null,
                'EmailAddress' => $customer['EmailAddress'] ?? null,
                'Phone1' => $customer['Phone1'] ?? null,
                'Address' => $customer['Address'] ?? null,
                'City' => $customer['City'] ?? null,
                'ZipCode' => $customer['ZipCode'] ?? null,
                'State' => $customer['State'] ?? null,
                'Country' => $customer['Country'] ?? null,
                $udf_field => $customer[$udf_field] ?? null,
                'BPAddresses_count' => count($customer['BPAddresses'] ?? [])
            ],
            'woo_mapped' => [
                'user_login' => $customer['CardCode'] ?? '',
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $display_name,
                'role' => 'customer',
                'meta' => [
                    'sapwc_cardcode' => $customer['CardCode'] ?? '',
                    'billing_company' => $customer['CardName'] ?? '',
                    'billing_email' => $customer['EmailAddress'] ?? '',
                    'billing_phone' => $customer['Phone1'] ?? '',
                    'billing_address_1' => $customer['Address'] ?? '',
                    'billing_city' => $customer['City'] ?? '',
                    'billing_postcode' => $customer['ZipCode'] ?? '',
                    'billing_state' => $customer['State'] ?? '',
                    'billing_country' => $customer['Country'] ?? 'ES',
                    'billing_nif' => $customer['FederalTaxID'] ?? '',
                    'company_name' => $customer['CardForeignName'] ?? '',
                    'shipping' => $shipping
                ]
            ],
            'status' => [
                'exists_in_woo' => $exists_in_woo,
                'has_email' => !empty($customer['EmailAddress']),
                'has_udf_flag' => ($customer[$udf_field] ?? '') === $udf_value,
                'would_import' => !$exists_in_woo && (($customer[$udf_field] ?? '') === $udf_value)
            ]
        ];
    }

    $client->logout();

    wp_send_json_success([
        'config' => [
            'udf_field' => $udf_field,
            'udf_value' => $udf_value,
            'mode' => get_option('sapwc_mode', 'ecommerce'),
            'send_welcome_email' => get_option('sapwc_send_welcome_email', '1') === '1'
        ],
        'customers' => $results,
        '_note' => $response['_debug_note'] ?? null,
        '_help' => 'Usa sapwc_debug_customer_mapping con cardcode específico para ver un cliente concreto'
    ]);
});

// AJAX para DEBUG: Dry-run de importación (simula sin crear usuario)
add_action('wp_ajax_sapwc_debug_import_dryrun', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sin permisos']);
    }

    $cardcode = isset($_POST['cardcode']) ? sanitize_text_field($_POST['cardcode']) : '';
    
    if (empty($cardcode)) {
        wp_send_json_error(['message' => 'Debes especificar un CardCode']);
    }

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => 'No hay conexión activa']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => 'Error de login: ' . $login['message']]);
    }

    $sap_data = $client->get("/BusinessPartners('{$cardcode}')");
    $client->logout();
    
    if (isset($sap_data['error'])) {
        wp_send_json_error(['message' => 'Cliente no encontrado en SAP: ' . ($sap_data['error']['message']['value'] ?? 'Error')]);
    }

    // Verificar si ya existe
    $exists_by_login = username_exists($cardcode);
    $exists_by_meta = get_users([
        'meta_key' => 'sapwc_cardcode',
        'meta_value' => $cardcode,
        'number' => 1,
        'fields' => 'ID'
    ]);

    if ($exists_by_login || !empty($exists_by_meta)) {
        $user_id = $exists_by_login ?: $exists_by_meta[0];
        $user = get_userdata($user_id);
        
        wp_send_json_success([
            'dry_run' => true,
            'action' => 'SKIP',
            'reason' => 'El cliente ya existe en WooCommerce',
            'existing_user' => [
                'ID' => $user_id,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'sapwc_cardcode' => get_user_meta($user_id, 'sapwc_cardcode', true),
                'welcome_email_sent' => get_user_meta($user_id, 'sapwc_welcome_email_sent', true) ?: 'No'
            ]
        ]);
        return;
    }

    // Simular el mapeo completo
    $full_name = $sap_data['CardName'] ?? $cardcode;
    $first_name = '';
    $last_name = '';

    if (strpos($full_name, ',') !== false) {
        [$last_name, $first_name] = array_map('trim', explode(',', $full_name, 2));
    } else {
        $parts = explode(' ', $full_name, 2);
        $first_name = $parts[0] ?? '';
        $last_name = $parts[1] ?? '';
    }

    $display_name = !empty($sap_data['CardForeignName']) 
        ? $sap_data['CardForeignName'] 
        : trim("{$first_name} {$last_name}");

    $email = sanitize_email($sap_data['EmailAddress'] ?? '');
    $email_status = 'OK';
    
    if (empty($email)) {
        $email = strtolower($cardcode) . '@cliente.temp';
        $email_status = 'TEMPORAL (sin email en SAP)';
    } elseif (email_exists($email)) {
        $email = strtolower($cardcode) . '.' . $email;
        $email_status = 'MODIFICADO (email ya en uso)';
    }

    // Datos de envío
    $shipping = ['_status' => 'Sin dirección de envío'];
    if (!empty($sap_data['BPAddresses']) && is_array($sap_data['BPAddresses'])) {
        foreach ($sap_data['BPAddresses'] as $addr) {
            if (($addr['AddressType'] ?? '') === 'bo_ShipTo') {
                $shipping = [
                    'shipping_address_1' => $addr['Street'] ?? $addr['Address'] ?? '',
                    'shipping_city' => $addr['City'] ?? '',
                    'shipping_postcode' => $addr['ZipCode'] ?? '',
                    'shipping_state' => function_exists('get_valid_wc_state') && !empty($addr['State']) 
                        ? get_valid_wc_state($addr['State']) 
                        : ($addr['State'] ?? ''),
                    'shipping_country' => $addr['Country'] ?? 'ES',
                    '_status' => 'OK'
                ];
                break;
            }
        }
    }

    // Mapeo de estado de facturación
    $billing_state = function_exists('get_valid_wc_state') && !empty($sap_data['State']) 
        ? get_valid_wc_state($sap_data['State']) 
        : ($sap_data['State'] ?? '');

    $udf_field = get_option('sapwc_customer_udf_field', 'U_ARTES_CLIW');
    $udf_value = get_option('sapwc_customer_udf_value', 'S');
    $has_udf = ($sap_data[$udf_field] ?? '') === $udf_value;

    wp_send_json_success([
        'dry_run' => true,
        'action' => $has_udf ? 'WOULD_IMPORT' : 'SKIP_NO_UDF',
        'udf_check' => [
            'field' => $udf_field,
            'expected' => $udf_value,
            'actual' => $sap_data[$udf_field] ?? '(no existe)',
            'pass' => $has_udf
        ],
        'user_to_create' => [
            'user_login' => $cardcode,
            'user_email' => $email,
            'email_status' => $email_status,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name,
            'role' => 'customer'
        ],
        'user_meta' => [
            'sapwc_cardcode' => $cardcode,
            'billing_company' => $sap_data['CardName'] ?? '',
            'billing_email' => sanitize_email($sap_data['EmailAddress'] ?? ''),
            'billing_phone' => $sap_data['Phone1'] ?? '',
            'billing_address_1' => $sap_data['Address'] ?? '',
            'billing_city' => $sap_data['City'] ?? '',
            'billing_postcode' => $sap_data['ZipCode'] ?? '',
            'billing_state' => $billing_state,
            'billing_state_raw' => $sap_data['State'] ?? '',
            'billing_country' => $sap_data['Country'] ?? 'ES',
            'billing_nif' => $sap_data['FederalTaxID'] ?? '',
            'company_name' => $sap_data['CardForeignName'] ?? ''
        ],
        'shipping_meta' => $shipping,
        'welcome_email' => [
            'would_send' => get_option('sapwc_send_welcome_email', '1') === '1',
            'to' => $email,
            'reset_password_link' => 'Se generará al crear el usuario'
        ],
        'sap_raw_fields' => array_keys($sap_data)
    ]);
});

// AJAX para sincronizar clientes B2B manualmente
add_action('wp_ajax_sapwc_sync_customers_now', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => 'Sin permisos suficientes']);
    }

    // Verificar modo B2B
    if (get_option('sapwc_mode', 'ecommerce') !== 'b2b') {
        wp_send_json_error(['message' => 'Esta función solo está disponible en modo B2B']);
    }

    $result = SAPWC_Customer_Sync::sync_all_pending();

    if (!empty($result['locked'])) {
        wp_send_json_error(['message' => 'Sincronización ya en curso. Espera a que termine.']);
    }

    if (!empty($result['success'])) {
        wp_send_json_success([
            'message' => $result['message'],
            'imported' => $result['imported'] ?? 0,
            'errors' => $result['errors'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
            'last_sync' => get_option('sapwc_customers_last_sync', '')
        ]);
    } else {
        wp_send_json_error(['message' => $result['message'] ?? 'Error desconocido']);
    }
});

// AJAX para vista previa del email de bienvenida
add_action('wp_ajax_sapwc_preview_welcome_email', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sin permisos']);
    }

    $html = SAPWC_Welcome_Mailer::get_preview();
    wp_send_json_success(['html' => $html]);
});

// AJAX para enviar email de prueba de bienvenida
add_action('wp_ajax_sapwc_send_test_welcome_email', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sin permisos']);
    }

    $html       = SAPWC_Welcome_Mailer::get_preview();
    $to         = get_option('admin_email');
    $site_name  = get_bloginfo('name');
    $domain     = wp_parse_url(home_url(), PHP_URL_HOST);
    $from_email = 'noreply@' . $domain;
    $subject    = sprintf(__('Vista previa: Email bienvenida - %s', 'sapwoo'), $site_name);
    $headers    = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . $from_email . '>',
        'Reply-To: ' . $to,
    ];
    $sent = wp_mail($to, $subject, $html, $headers);

    if ($sent) {
        wp_send_json_success(['message' => sprintf(__('Email de prueba enviado a %s', 'sapwoo'), $to)]);
    } else {
        wp_send_json_error(['message' => __('Error al enviar el email de prueba.', 'sapwoo')]);
    }
    wp_die();
});

// AJAX para reenviar email de bienvenida a un usuario
add_action('wp_ajax_sapwc_resend_welcome_email', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_users')) {
        wp_send_json_error(['message' => 'Sin permisos']);
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) {
        wp_send_json_error(['message' => 'ID de usuario no proporcionado']);
    }

    $sent = SAPWC_Welcome_Mailer::resend($user_id);
    
    if ($sent) {
        wp_send_json_success(['message' => 'Email reenviado correctamente']);
    } else {
        wp_send_json_error(['message' => 'Error al enviar el email']);
    }
});

// Cron de sincronización de clientes B2B
add_action('sapwc_cron_sync_customers', ['SAPWC_Customer_Sync', 'cron_callback']);

// Programar cron de clientes al cambiar la opción
add_action('update_option_sapwc_sync_customers_auto', function ($old, $new) {
    if ($old !== $new) {
        // Limpiar cron existente
        wp_clear_scheduled_hook('sapwc_cron_sync_customers');
        
        if ($new === '1') {
            // Programar nuevo cron
            sapwc_schedule_customer_sync_cron();
        }
    }
}, 10, 2);

// Programar cron de clientes al cambiar la hora
add_action('update_option_sapwc_customer_sync_time', function ($old, $new) {
    if ($old !== $new && get_option('sapwc_sync_customers_auto', '0') === '1') {
        wp_clear_scheduled_hook('sapwc_cron_sync_customers');
        sapwc_schedule_customer_sync_cron();
    }
}, 10, 2);

/**
 * Programa el cron de sincronización de clientes para la hora configurada
 */
function sapwc_schedule_customer_sync_cron() {
    if (wp_next_scheduled('sapwc_cron_sync_customers')) {
        return; // Ya está programado
    }

    $sync_time = get_option('sapwc_customer_sync_time', '10:00');
    $parts = explode(':', $sync_time);
    $hour = intval($parts[0] ?? 10);
    $minute = intval($parts[1] ?? 0);

    // Calcular el timestamp para hoy a la hora configurada
    $today = wp_date('Y-m-d');
    $scheduled_time = strtotime("{$today} {$hour}:{$minute}:00");

    // Si la hora ya pasó hoy, programar para mañana
    if ($scheduled_time <= time()) {
        $scheduled_time = strtotime('+1 day', $scheduled_time);
    }

    wp_schedule_event($scheduled_time, 'daily', 'sapwc_cron_sync_customers');
    
    error_log('[SAPWC] Cron de clientes programado para: ' . wp_date('Y-m-d H:i:s', $scheduled_time));
}

// Inicializar cron de clientes si está habilitado
add_action('init', function () {
    if (get_option('sapwc_sync_customers_auto', '0') === '1' && get_option('sapwc_mode', 'ecommerce') === 'b2b') {
        if (!wp_next_scheduled('sapwc_cron_sync_customers')) {
            sapwc_schedule_customer_sync_cron();
        }
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



function sapwc_sync_orders_with_lock()
{
    // Lock para evitar ejecuciones solapadas (20 min)
    if (get_transient('sapwc_cron_orders_lock')) {
        error_log('[SAPWC] Sincronización de pedidos ya en ejecución, evitando solapamiento.');
        return [
            'locked' => true,
            'message' => 'Sincronización en curso.'
        ];
    }
    set_transient('sapwc_cron_orders_lock', 1, 20 * MINUTE_IN_SECONDS);

    try {
        $conn = sapwc_get_active_connection();
        if (!$conn) {
            error_log(__('[SAPWC] No hay conexión activa configurada.', 'sapwoo'));
            return ['success' => false, 'message' => 'No hay conexión activa configurada.'];
        }

        $client = new SAPWC_API_Client($conn['url']);
        $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
        if (!$login['success']) {
            error_log(__('[SAPWC] Error de conexión: ', 'sapwoo') . $login['message']);
            return ['success' => false, 'message' => 'Error de conexión a SAP: ' . $login['message']];
        }

        $sync = new SAPWC_Sync_Handler($client);
        $orders = wc_get_orders([
            'status' => ['processing', 'on-hold'],
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

        update_option('sapwc_orders_last_sync', wp_date('Y-m-d H:i:s'));
        if ($last_docentry) {
            update_option('sapwc_orders_last_docentry', $last_docentry);
        }

        error_log(sprintf(__('Enviados: %d | Ya enviados: %d | Errores: %d', 'sapwoo'), $sent, $skipped, $errors));
        SAPWC_Logger::log(null, 'cron', 'info', "Finalizó sync automática. Enviados: $sent | Saltados: $skipped | Errores: $errors");

        return [
            'success' => true,
            'sent' => $sent,
            'skipped' => $skipped,
            'errors' => $errors,
            'last_docentry' => $last_docentry
        ];
    } finally {
        delete_transient('sapwc_cron_orders_lock');
    }
}
add_action('sapwc_cron_sync_orders', function () {
    $result = sapwc_sync_orders_with_lock();
    if (!empty($result['locked'])) {
        // Si quieres loguear: 
        error_log('[SAPWC] Se evitó un solapamiento de sincro de pedidos.');
    }
});








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

    $next_cron = wp_next_scheduled('sapwc_cron_sync_orders');
    $next_cron_formatted = $next_cron ? wp_date('Y-m-d H:i:s', $next_cron) : __('No programado', 'sapwoo');

    $wp_admin_bar->add_node([
        'id'     => 'sapwc_status_nextcron',
        'parent' => 'sapwc_status',
        'title'  => '<span class="dashicons dashicons-clock" style="font-family: dashicons;"></span> Próx. Cron: <strong>' . esc_html($next_cron_formatted) . '</strong>',
        'meta'   => ['title' => 'Próxima ejecución del cron de pedidos']
    ]);
    $wp_admin_bar->add_node([
        'id'     => 'sapwc_status_action',
        'parent' => 'sapwc_status',
        'title'  => '<span class="dashicons dashicons-update-alt" style="font-family: dashicons"></span> <span id="sapwc-sync-trigger">Sincronizar Pedidos</span>',
        'href'   => '#',
        'meta'   => ['onclick' => 'return false;']
    ]);
    
}, 100);






// AJAX para enviar pedidos a SAP
add_action('wp_ajax_sapwc_send_orders', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');
    $result = sapwc_sync_orders_with_lock();

    if (!empty($result['locked'])) {
        wp_send_json_error(['message' => 'Sincronización ya en curso. Espera a que termine.']);
    } elseif (empty($result['success'])) {
        wp_send_json_error(['message' => $result['message'] ?? 'Error desconocido']);
    } else {
        wp_send_json_success([
            'message' => __('Pedidos sincronizados correctamente.', 'sapwoo'),
            'sent' => $result['sent'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'last_docentry' => $result['last_docentry'],
            'last_sync' => wp_date('Y-m-d H:i:s')
        ]);
    }
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


// Ajustar decimales de precios en WooCommerce
/*-------------------AJUSTE DECIMALES WOO------------------------*/
add_filter('wc_get_price_decimals', function () {
    return 2; // 
});
add_filter('woocommerce_get_price_html', function ($price_html, $product) {
    $precio = floatval($product->get_price());
    return wc_price(round($precio, 2));
}, 10, 2);

add_filter('woocommerce_calculated_total', function ($total) {
    return round($total, 2);
});

// Recalcular precios cuando cambie la dirección de envío (para aplicar tarifas regionales)
add_action('woocommerce_checkout_update_order_review', 'sapwc_maybe_recalculate_prices_on_address_change');
add_action('woocommerce_cart_calculate_fees', 'sapwc_apply_regional_pricing');

/**
 * Recalcula precios basado en la región de envío para aplicar tarifas correctas
 */
function sapwc_maybe_recalculate_prices_on_address_change($posted_data) {
    if (is_admin() || !WC()->cart) return;
    
    // Solo en modo ecommerce
    $mode = get_option('sapwc_mode', 'ecommerce');
    if ($mode !== 'ecommerce') return;
    
    // Forzar recálculo del carrito
    WC()->cart->calculate_totals();
}

/**
 * Aplica precios basados en la región de envío usando las tarifas configuradas
 */
function sapwc_apply_regional_pricing() {
    if (is_admin() || !WC()->cart) return;
    
    // Solo en modo ecommerce
    $mode = get_option('sapwc_mode', 'ecommerce');
    if ($mode !== 'ecommerce') return;
    
    // Obtener información de envío del usuario
    $customer = WC()->customer;
    if (!$customer) return;
    
    $shipping_country = $customer->get_shipping_country();
    $shipping_state = $customer->get_shipping_state();
    
    // Determinar tarifa regional - Portugal y Canarias independientes
    $target_country = strtoupper($shipping_country ?: $customer->get_billing_country());
    $target_state = strtoupper($shipping_state ?: $customer->get_billing_state());
    
    $regional_tariff = null;
    if ($target_country === 'PT') {
        // Portugal: usar tarifa específica de Portugal
        $regional_tariff = get_option('sapwc_tariff_portugal', '');
    } elseif (in_array($target_state, ['GC', 'TF', 'LP', 'HI', 'TE', 'CN'])) {
        // Canarias: tarifa específica de Canarias
        $regional_tariff = get_option('sapwc_tariff_canarias', '');
    } else {
        // Península y Baleares: tarifa específica de península
        $regional_tariff = get_option('sapwc_tariff_peninsula', '');
    }
    
    // Si no hay tarifa regional configurada, no hacer nada
    if (empty($regional_tariff)) return;
    
    // Aquí podrías implementar la lógica para actualizar precios del carrito
    // usando la tarifa regional si tienes acceso a la API de SAP desde el frontend
    // Por ahora, solo lo documentamos para que se aplique en el momento del checkout
}
