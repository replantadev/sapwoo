<?php
/**
 * Sincronización de Clientes SAP → WooCommerce
 * 
 * Importa automáticamente clientes marcados como "cliente web" en SAP
 * al sistema de usuarios de WooCommerce (modo B2B únicamente).
 * 
 * @package SAPWC
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAPWC_Customer_Sync
{
    /**
     * Campo UDF en SAP que indica si es cliente web
     */
    private static function get_udf_field()
    {
        return get_option('sapwc_customer_udf_field', 'U_ARTES_CLIW');
    }

    /**
     * Valor que indica "Sí" en el campo UDF
     */
    private static function get_udf_value()
    {
        return get_option('sapwc_customer_udf_value', 'S');
    }

    /**
     * Obtiene clientes web desde SAP que aún no están en WooCommerce
     * 
     * @param int $limit Máximo de clientes a obtener
     * @return array Lista de clientes de SAP
     */
    public static function get_pending_web_customers($limit = 50, $show_all = false)
    {
        $conn = sapwc_get_active_connection();
        if (!$conn) {
            return ['error' => __('No hay conexión activa con SAP.', 'sapwoo')];
        }

        $client = new SAPWC_API_Client($conn['url']);
        $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

        if (!$login['success']) {
            return ['error' => __('Error de login SAP: ', 'sapwoo') . $login['message']];
        }

        $udf_field = self::get_udf_field();
        $udf_value = self::get_udf_value();

        // Campos a obtener
        $select = 'CardCode,CardName,CardForeignName,FederalTaxID,EmailAddress,Phone1,Address,City,ZipCode,State,Country,BPAddresses';
        
        $all_customers = [];
        $skip = 0;
        $batch_size = 20;

        // Intentar con filtro UDF primero (omitir si show_all = true)
        $use_udf_filter = !$show_all && !empty($udf_field) && !empty($udf_value);
        $filter_failed = false;

        do {
            if ($use_udf_filter && !$filter_failed) {
                $filter = urlencode("{$udf_field} eq '{$udf_value}'");
                $query = "/BusinessPartners?\$filter={$filter}&\$select={$select}&\$top={$batch_size}&\$skip={$skip}";
            } else {
                // Fallback: obtener todos los clientes (CardType = 'cCustomer')
                $filter = urlencode("CardType eq 'cCustomer'");
                $query = "/BusinessPartners?\$filter={$filter}&\$select={$select}&\$top={$batch_size}&\$skip={$skip}";
            }

            $response = $client->get($query);

            // Si el filtro UDF falla, intentar sin él
            if (!isset($response['value']) && $use_udf_filter && !$filter_failed) {
                $filter_failed = true;
                $filter = urlencode("CardType eq 'cCustomer'");
                $query = "/BusinessPartners?\$filter={$filter}&\$select={$select}&\$top={$batch_size}&\$skip={$skip}";
                $response = $client->get($query);
            }

            if (!isset($response['value']) || empty($response['value'])) {
                break;
            }

            foreach ($response['value'] as $customer) {
                // Verificar si ya existe en WooCommerce
                if (!self::customer_exists_in_woo($customer['CardCode'])) {
                    $all_customers[] = $customer;
                    
                    // Respetar el límite
                    if (count($all_customers) >= $limit) {
                        break 2;
                    }
                }
            }

            $skip += $batch_size;
            
            // Máximo para evitar loops infinitos
        } while (count($response['value']) === $batch_size && $skip < 10000);

        $client->logout();

        return $all_customers;
    }

    /**
     * Verifica si un cliente ya existe en WooCommerce
     * 
     * @param string $cardcode CardCode de SAP
     * @return bool
     */
    public static function customer_exists_in_woo($cardcode)
    {
        // Verificar por username (CardCode)
        if (username_exists($cardcode)) {
            return true;
        }

        // Verificar por meta sapwc_cardcode
        $users = get_users([
            'meta_key' => 'sapwc_cardcode',
            'meta_value' => $cardcode,
            'number' => 1,
            'fields' => 'ID'
        ]);

        return !empty($users);
    }

    /**
     * Importa un cliente de SAP a WooCommerce
     * 
     * @param array $customer_data Datos del cliente desde SAP
     * @param bool $send_welcome_email Enviar email de bienvenida
     * @return array|WP_Error Resultado de la importación
     */
    public static function import_customer($customer_data, $send_welcome_email = true)
    {
        $cardcode = $customer_data['CardCode'] ?? '';
        
        if (empty($cardcode)) {
            return new WP_Error('missing_cardcode', 'CardCode no proporcionado');
        }

        // Verificar si ya existe
        if (self::customer_exists_in_woo($cardcode)) {
            return new WP_Error('customer_exists', "El cliente {$cardcode} ya existe en WooCommerce");
        }

        // Parsear nombre y apellidos desde CardName
        $full_name = $customer_data['CardName'] ?? $cardcode;
        $first_name = '';
        $last_name = '';

        if (strpos($full_name, ',') !== false) {
            [$last_name, $first_name] = array_map('trim', explode(',', $full_name, 2));
        } else {
            $parts = explode(' ', $full_name, 2);
            $first_name = $parts[0] ?? '';
            $last_name = $parts[1] ?? '';
        }

        // Usar nombre comercial si existe
        $display_name = !empty($customer_data['CardForeignName']) 
            ? $customer_data['CardForeignName'] 
            : trim("{$first_name} {$last_name}");

        // Generar email si no existe
        $email = sanitize_email($customer_data['EmailAddress'] ?? '');
        if (empty($email)) {
            // Crear email temporal basado en CardCode
            $email = strtolower($cardcode) . '@cliente.temp';
        }

        // Verificar si el email ya está en uso
        $existing_user_by_email = email_exists($email);
        if ($existing_user_by_email) {
            // Añadir sufijo al email
            $email = strtolower($cardcode) . '.' . $email;
        }

        // Crear usuario con contraseña aleatoria
        $random_password = wp_generate_password(16, true, true);
        
        $user_data = [
            'user_login'   => $cardcode,
            'user_pass'    => $random_password,
            'user_email'   => $email,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $display_name,
            'role'         => 'customer'
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            error_log('[SAPWC Customer Sync] Error al crear usuario ' . $cardcode . ': ' . $user_id->get_error_message());
            return $user_id;
        }

        // Guardar metadatos del cliente
        self::save_customer_meta($user_id, $customer_data);

        // Log de éxito
        SAPWC_Logger::log(null, 'customer_import', 'success', "Cliente {$cardcode} importado correctamente (User ID: {$user_id})");

        // Enviar email de bienvenida si está habilitado
        if ($send_welcome_email && get_option('sapwc_send_welcome_email', '1') === '1') {
            self::send_welcome_email($user_id);
        }

        return [
            'success' => true,
            'user_id' => $user_id,
            'cardcode' => $cardcode,
            'email' => $email
        ];
    }

    /**
     * Guarda los metadatos del cliente en WooCommerce
     * 
     * @param int $user_id ID del usuario
     * @param array $data Datos de SAP
     */
    private static function save_customer_meta($user_id, $data)
    {
        // Meta principal para identificar cliente SAP
        update_user_meta($user_id, 'sapwc_cardcode', $data['CardCode']);
        update_user_meta($user_id, 'sapwc_imported_at', current_time('mysql'));

        // Datos de facturación
        update_user_meta($user_id, 'billing_company', $data['CardName'] ?? '');
        update_user_meta($user_id, 'billing_email', sanitize_email($data['EmailAddress'] ?? ''));
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($data['Phone1'] ?? ''));
        update_user_meta($user_id, 'billing_address_1', $data['Address'] ?? '');
        update_user_meta($user_id, 'billing_city', $data['City'] ?? '');
        update_user_meta($user_id, 'billing_postcode', $data['ZipCode'] ?? '');
        update_user_meta($user_id, 'billing_country', $data['Country'] ?? 'ES');
        
        // Mapear estado/provincia si existe la función
        if (function_exists('get_valid_wc_state') && !empty($data['State'])) {
            update_user_meta($user_id, 'billing_state', get_valid_wc_state($data['State']));
        } else {
            update_user_meta($user_id, 'billing_state', $data['State'] ?? '');
        }

        // NIF/CIF
        if (!empty($data['FederalTaxID'])) {
            update_user_meta($user_id, 'billing_nif', $data['FederalTaxID']);
            update_user_meta($user_id, 'nif', $data['FederalTaxID']); // Compatibilidad
        }

        // Nombre comercial
        if (!empty($data['CardForeignName'])) {
            update_user_meta($user_id, 'company_name', $data['CardForeignName']);
        }

        // Direcciones de envío desde BPAddresses
        if (!empty($data['BPAddresses']) && is_array($data['BPAddresses'])) {
            foreach ($data['BPAddresses'] as $bp_addr) {
                if (($bp_addr['AddressType'] ?? '') === 'bo_ShipTo') {
                    update_user_meta($user_id, 'shipping_address_1', $bp_addr['Street'] ?? $bp_addr['Address'] ?? '');
                    update_user_meta($user_id, 'shipping_city', $bp_addr['City'] ?? '');
                    update_user_meta($user_id, 'shipping_postcode', $bp_addr['ZipCode'] ?? '');
                    update_user_meta($user_id, 'shipping_country', $bp_addr['Country'] ?? 'ES');
                    
                    if (function_exists('get_valid_wc_state') && !empty($bp_addr['State'])) {
                        update_user_meta($user_id, 'shipping_state', get_valid_wc_state($bp_addr['State']));
                    }
                    break; // Solo la primera dirección de envío
                }
            }
        }
    }

    /**
     * Envía el email de bienvenida con link para establecer contraseña
     * 
     * @param int $user_id ID del usuario
     * @return bool
     */
    public static function send_welcome_email($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Usar la clase de mailer si existe
        if (class_exists('SAPWC_Welcome_Mailer')) {
            return SAPWC_Welcome_Mailer::send($user_id);
        }

        // Fallback: usar el sistema nativo de WordPress
        // Generar key para reset de contraseña
        $key = get_password_reset_key($user);
        
        if (is_wp_error($key)) {
            error_log('[SAPWC Customer Sync] Error generando reset key para ' . $user->user_login);
            return false;
        }

        $reset_url = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user->user_login), 'login');

        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Tu cuenta ha sido creada', 'sapwoo'), $site_name);
        
        $message = sprintf(__('Hola %s,', 'sapwoo'), $user->first_name ?: $user->display_name) . "\n\n";
        $message .= sprintf(__('Se ha creado una cuenta para ti en %s.', 'sapwoo'), $site_name) . "\n\n";
        $message .= __('Para acceder, primero debes establecer tu contraseña:', 'sapwoo') . "\n\n";
        $message .= $reset_url . "\n\n";
        $message .= __('Este enlace es válido por 24 horas.', 'sapwoo') . "\n\n";
        $message .= sprintf(__('Tu nombre de usuario es: %s', 'sapwoo'), $user->user_login) . "\n\n";
        $message .= __('¡Te esperamos!', 'sapwoo') . "\n";
        $message .= $site_name;

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($user->user_email, $subject, $message, $headers);

        if ($sent) {
            update_user_meta($user_id, 'sapwc_welcome_email_sent', current_time('mysql'));
            SAPWC_Logger::log(null, 'welcome_email', 'success', "Email de bienvenida enviado a {$user->user_email}");
        } else {
            SAPWC_Logger::log(null, 'welcome_email', 'error', "Error al enviar email a {$user->user_email}");
        }

        return $sent;
    }

    /**
     * Sincroniza todos los clientes web pendientes
     * 
     * @return array Resultado de la sincronización
     */
    public static function sync_all_pending()
    {
        // Solo en modo B2B
        if (get_option('sapwc_mode', 'ecommerce') !== 'b2b') {
            return [
                'success' => false,
                'message' => 'La sincronización de clientes solo está disponible en modo B2B'
            ];
        }

        // Lock para evitar ejecuciones simultáneas
        if (get_transient('sapwc_customer_sync_lock')) {
            return [
                'success' => false,
                'locked' => true,
                'message' => 'Sincronización ya en curso'
            ];
        }

        set_transient('sapwc_customer_sync_lock', 1, 15 * MINUTE_IN_SECONDS);

        $results = [
            'success' => true,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => []
        ];

        try {
            $pending_customers = self::get_pending_web_customers(100);

            if (empty($pending_customers)) {
                $results['message'] = 'No hay clientes web pendientes de importar';
                return $results;
            }

            $send_email = get_option('sapwc_send_welcome_email', '1') === '1';

            foreach ($pending_customers as $customer) {
                $result = self::import_customer($customer, $send_email);

                if (is_wp_error($result)) {
                    $results['errors']++;
                    $results['details'][] = [
                        'cardcode' => $customer['CardCode'],
                        'status' => 'error',
                        'message' => $result->get_error_message()
                    ];
                } elseif (isset($result['success']) && $result['success']) {
                    $results['imported']++;
                    $results['details'][] = [
                        'cardcode' => $customer['CardCode'],
                        'status' => 'imported',
                        'user_id' => $result['user_id']
                    ];
                } else {
                    $results['skipped']++;
                }

                // Pequeña pausa para no saturar
                usleep(100000); // 0.1 segundos
            }

            $results['message'] = sprintf(
                'Sincronización completada: %d importados, %d errores, %d omitidos',
                $results['imported'],
                $results['errors'],
                $results['skipped']
            );

            // Actualizar timestamp de última sincronización
            update_option('sapwc_customers_last_sync', current_time('mysql'));

            SAPWC_Logger::log(null, 'customer_sync', 'success', $results['message']);

        } catch (Exception $e) {
            $results['success'] = false;
            $results['message'] = 'Error durante la sincronización: ' . $e->getMessage();
            SAPWC_Logger::log(null, 'customer_sync', 'error', $results['message']);
        } finally {
            delete_transient('sapwc_customer_sync_lock');
        }

        return $results;
    }

    /**
     * Callback para el cron de sincronización
     */
    public static function cron_callback()
    {
        // Verificar si está habilitada la sincronización automática
        if (get_option('sapwc_sync_customers_auto', '0') !== '1') {
            return;
        }

        // Solo en modo B2B
        if (get_option('sapwc_mode', 'ecommerce') !== 'b2b') {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAPWC] Ejecutando sincronización automática de clientes web');
        }
        
        $result = self::sync_all_pending();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAPWC] Resultado: ' . ($result['message'] ?? json_encode($result)));
        }
    }

    /**
     * Obtiene estadísticas de clientes sincronizados
     * 
     * @return array
     */
    public static function get_stats()
    {
        global $wpdb;

        $total_imported = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'sapwc_cardcode'"
        );

        $last_sync = get_option('sapwc_customers_last_sync', __('Nunca', 'sapwoo'));

        $emails_sent = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'sapwc_welcome_email_sent'"
        );

        return [
            'total_imported' => (int) $total_imported,
            'last_sync' => $last_sync,
            'emails_sent' => (int) $emails_sent
        ];
    }

    /**
     * Obtiene el preview de campos de un cliente de SAP
     *
     * @param string $cardcode CardCode del cliente
     * @return array
     */
    public static function get_customer_preview($cardcode)
    {
        $conn = sapwc_get_active_connection();
        if (!$conn) {
            return ['error' => __('No hay conexión activa con SAP.', 'sapwoo')];
        }

        $client = new SAPWC_API_Client($conn['url']);
        $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

        if (!$login['success']) {
            return ['error' => __('Error de login SAP: ', 'sapwoo') . $login['message']];
        }

        $cardcode_enc = urlencode($cardcode);
        $select = 'CardCode,CardName,CardForeignName,FederalTaxID,EmailAddress,Phone1,Address,City,ZipCode,State,Country,BPAddresses';
        $query = "/BusinessPartners('{$cardcode_enc}')?\$select={$select}";
        $response = $client->get($query);
        $client->logout();

        if (empty($response) || isset($response['error'])) {
            return ['error' => __('Cliente no encontrado en SAP.', 'sapwoo')];
        }

        // Verificar si ya existe
        $exists = self::customer_exists_in_woo($cardcode);
        $existing_user = null;
        if ($exists) {
            $users = get_users([
                'meta_key' => 'sapwc_cardcode',
                'meta_value' => $cardcode,
                'number' => 1
            ]);
            if (!empty($users)) {
                $existing_user = $users[0];
            } else {
                $existing_user = get_user_by('login', $cardcode);
            }
        }

        // Parsear nombre
        $full_name = $response['CardName'] ?? '';
        $first_name = '';
        $last_name = '';
        if (strpos($full_name, ',') !== false) {
            [$last_name, $first_name] = array_map('trim', explode(',', $full_name, 2));
        } else {
            $parts = explode(' ', $full_name, 2);
            $first_name = $parts[0] ?? '';
            $last_name = $parts[1] ?? '';
        }

        // Dirección de envío
        $shipping_addr = '';
        if (!empty($response['BPAddresses']) && is_array($response['BPAddresses'])) {
            foreach ($response['BPAddresses'] as $bp_addr) {
                if (($bp_addr['AddressType'] ?? '') === 'bo_ShipTo') {
                    $shipping_addr = implode(', ', array_filter([
                        $bp_addr['Street'] ?? $bp_addr['Address'] ?? '',
                        $bp_addr['City'] ?? '',
                        $bp_addr['ZipCode'] ?? ''
                    ]));
                    break;
                }
            }
        }

        return [
            'sap_data' => [
                ['field' => 'CardCode', 'label' => 'Código Cliente', 'value' => $response['CardCode'] ?? ''],
                ['field' => 'CardName', 'label' => 'Nombre / Razón Social', 'value' => $response['CardName'] ?? ''],
                ['field' => 'CardForeignName', 'label' => 'Nombre Comercial', 'value' => $response['CardForeignName'] ?? ''],
                ['field' => 'FederalTaxID', 'label' => 'NIF/CIF', 'value' => $response['FederalTaxID'] ?? ''],
                ['field' => 'EmailAddress', 'label' => 'Email', 'value' => $response['EmailAddress'] ?? ''],
                ['field' => 'Phone1', 'label' => 'Teléfono', 'value' => $response['Phone1'] ?? ''],
                ['field' => 'Address', 'label' => 'Dirección', 'value' => $response['Address'] ?? ''],
                ['field' => 'City', 'label' => 'Ciudad', 'value' => $response['City'] ?? ''],
                ['field' => 'ZipCode', 'label' => 'Código Postal', 'value' => $response['ZipCode'] ?? ''],
                ['field' => 'State', 'label' => 'Provincia', 'value' => $response['State'] ?? ''],
                ['field' => 'BPAddresses', 'label' => 'Dir. Envío', 'value' => $shipping_addr ?: '-'],
            ],
            'woo_mapping' => [
                ['field' => 'user_login', 'label' => 'Usuario', 'source' => 'CardCode'],
                ['field' => 'first_name', 'label' => 'Nombre', 'source' => $first_name],
                ['field' => 'last_name', 'label' => 'Apellidos', 'source' => $last_name],
                ['field' => 'user_email', 'label' => 'Email', 'source' => $response['EmailAddress'] ?: "{$cardcode}@cliente.temp"],
                ['field' => 'display_name', 'label' => 'Nombre Mostrado', 'source' => $response['CardForeignName'] ?: trim("{$first_name} {$last_name}")],
                ['field' => 'billing_company', 'label' => 'Empresa (Facturación)', 'source' => 'CardName'],
                ['field' => 'billing_nif', 'label' => 'NIF (Facturación)', 'source' => 'FederalTaxID'],
                ['field' => 'billing_phone', 'label' => 'Teléfono (Facturación)', 'source' => 'Phone1'],
                ['field' => 'billing_address_1', 'label' => 'Dirección (Facturación)', 'source' => 'Address'],
                ['field' => 'billing_city', 'label' => 'Ciudad (Facturación)', 'source' => 'City'],
                ['field' => 'shipping_address_1', 'label' => 'Dirección (Envío)', 'source' => 'BPAddresses[bo_ShipTo]'],
            ],
            'exists_in_woo' => $exists,
            'existing_user_id' => $existing_user ? $existing_user->ID : null,
            'existing_user_email' => $existing_user ? $existing_user->user_email : null,
            'raw' => $response,
        ];
    }

    /**
     * Obtiene clientes de SAP que no existen en WooCommerce
     *
     * @param int $skip Número de registros a saltar
     * @param int $top Número máximo de registros
     * @return array
     */
    public static function get_pending_customers($skip = 0, $top = 50)
    {
        $result = self::get_pending_web_customers($skip, $top);

        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $sap_items = $result['items'] ?? [];
        $pending = [];

        foreach ($sap_items as $item) {
            $cardcode = $item['CardCode'] ?? '';
            if (empty($cardcode)) {
                continue;
            }

            // Verificar si ya existe en WooCommerce
            if (!self::customer_exists_in_woo($cardcode)) {
                $pending[] = $item;
            }
        }

        return [
            'items' => $pending,
            'total_sap' => count($sap_items),
            'total_pending' => count($pending)
        ];
    }
}
