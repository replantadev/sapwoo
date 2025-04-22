<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SAPWC_Settings_Page
{
    public static function render()
    {
        $connections = get_option('sapwc_connections', []);
        $active_index = get_option('sapwc_connection_index', 0);
        $active = $connections[$active_index] ?? [
            'url' => '',
            'user' => '',
            'pass' => '',
            'db' => '',
            'name' => __('Conexión Principal', 'sapwoo'),
            'version' => '',
            'ssl' => false
        ];
        if (isset($_POST['sapwc_save_credentials'])) {
            check_admin_referer('sapwc_save_credentials_action', 'sapwc_save_credentials_nonce');

            $connections[$active_index] = [
                'url' => sanitize_text_field($_POST['sapwc_url']),
                'user' => sanitize_text_field($_POST['sapwc_user']),
                'pass' => sanitize_text_field($_POST['sapwc_pass']),
                'db'   => sanitize_text_field($_POST['sapwc_db']),
                'name' => sanitize_text_field($_POST['sapwc_name']),
                'version' => sanitize_text_field($_POST['sapwc_version']),
                'ssl'     => isset($_POST['sapwc_ssl']) ? true : false
            ];
            update_option('sapwc_connections', $connections);
            update_option('sapwc_connection_index', $active_index); // guardar el índice activo

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Conexión actualizada correctamente.', 'sapwoo') . '</p></div>';
            $active = $connections[$active_index]; // refrescar
        }

        echo '<div class="wrap"><h1>' . esc_html__('Credenciales SAP', 'sapwoo') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('sapwc_save_credentials_action', 'sapwc_save_credentials_nonce');
        echo '<table class="form-table">
        <tr><th scope="row">' . esc_html__('Nombre de la conexión', 'sapwoo') . '</th><td><input type="text" name="sapwc_name" value="' . esc_attr($active['name']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">' . esc_html__('URL SAP', 'sapwoo') . '</th><td><input type="text" name="sapwc_url" value="' . esc_attr($active['url']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">' . esc_html__('Usuario', 'sapwoo') . '</th><td><input type="text" name="sapwc_user" value="' . esc_attr($active['user']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">' . esc_html__('Contraseña', 'sapwoo') . '</th><td><input type="password" name="sapwc_pass" value="' . esc_attr($active['pass']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">' . esc_html__('Company DB', 'sapwoo') . '</th><td><input type="text" name="sapwc_db" value="' . esc_attr($active['db']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">' . esc_html__('Versión SAP', 'sapwoo') . '</th><td><input type="text" name="sapwc_version" value="' . esc_attr($active['version']) . '" class="regular-text" placeholder="' . esc_attr__('Ej: 10.0 FP 2111', 'sapwoo') . '"></td></tr>
        <tr><th scope="row">' . esc_html__('Usar verificación SSL', 'sapwoo') . '</th><td><label><input type="checkbox" name="sapwc_ssl" value="1" ' . checked(!empty($active['ssl']), true, false) . '> ' . esc_html__('Sí', 'sapwoo') . '</label></td></tr>
    </table>';
        submit_button(__('Guardar credenciales', 'sapwoo'), 'primary', 'sapwc_save_credentials');
        echo '</form>';

        echo '<h2>' . esc_html__('Probar conexión', 'sapwoo') . '</h2>';
        echo '<button id="sapwc-test-conn" class="button"><span class="dashicons dashicons-admin-network"></span> ' . esc_html__('Probar conexión', 'sapwoo') . '</button>';
        echo '<p id="sapwc-test-result" style="font-weight: bold;"></p>';
       
$conn = sapwc_get_active_connection();
$version_info = null;

if ($conn) {
    $client = new SAPWC_API_Client($conn['url']);
    
    $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    $version_info = $client->get_version_info();
}

if ($version_info): ?>
    <h2>ℹ️ <?php echo esc_html__('Información de SAP', 'sapwoo'); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row"><?php echo esc_html__('Versión de Service Layer', 'sapwoo'); ?></th>
            <td><?php echo esc_html($version_info['ServiceLayerVersion']); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php echo esc_html__('Versión de SAP B1', 'sapwoo'); ?></th>
            <td><?php echo esc_html($version_info['SAPB1Version']); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php echo esc_html__('Base de Datos', 'sapwoo'); ?></th>
            <td><?php echo esc_html($version_info['CompanyDB']); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php echo esc_html__('Tipo de BD', 'sapwoo'); ?></th>
            <td><?php echo esc_html($version_info['DatabaseType']); ?></td>
        </tr>
    </table>
<?php elseif ($conn): ?>
    <p style="color: red; font-weight: bold;">❌ <?php echo esc_html__('No se pudo obtener la versión de SAP.', 'sapwoo'); ?></p>
<?php endif; ?>
<?php
        echo '<hr>';
        echo '<h2>ℹ️ ' . esc_html__('Compatibilidad', 'sapwoo') . '</h2>';
        echo '<p>' . esc_html__('Este plugin ha sido probado y es compatible con:', 'sapwoo') . '</p>';
        echo '<ul style="margin-left:1.5em;">
            <li><strong>' . esc_html__('SAP Business One 10.0 FP 2111', 'sapwoo') . '</strong> (' . esc_html__('recomendado', 'sapwoo') . ')</li>
            <li><strong>' . esc_html__('SAP Business One 9.3 PL14', 'sapwoo') . '</strong> (' . esc_html__('funcionalidad limitada', 'sapwoo') . ')</li>
            <li>' . esc_html__('Requiere servicio', 'sapwoo') . ' <code>/b1s/v1</code> ' . esc_html__('disponible vía HTTPS', 'sapwoo') . '</li>
          </ul>';

        echo '</div>';
    }
}
