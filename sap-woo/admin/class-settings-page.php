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
            'name' => 'Conexión Principal',
            'version' => '',
            'ssl' => false
        ];
        if (isset($_POST['sapwc_save_credentials'])) {
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


            echo '<div class="updated"><p>Conexión actualizada correctamente.</p></div>';
            $active = $connections[$active_index]; // refrescar
        }

        echo '<div class="wrap"><h1>Credenciales SAP</h1>';
        echo '<form method="post">';
        echo '<table class="form-table">
        <tr><th scope="row">Nombre de la conexión</th><td><input type="text" name="sapwc_name" value="' . esc_attr($active['name']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">URL SAP</th><td><input type="text" name="sapwc_url" value="' . esc_attr($active['url']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">Usuario</th><td><input type="text" name="sapwc_user" value="' . esc_attr($active['user']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">Contraseña</th><td><input type="password" name="sapwc_pass" value="' . esc_attr($active['pass']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">Company DB</th><td><input type="text" name="sapwc_db" value="' . esc_attr($active['db']) . '" class="regular-text"></td></tr>
        <tr><th scope="row">Versión SAP</th><td><input type="text" name="sapwc_version" value="' . esc_attr($active['version']) . '" class="regular-text" placeholder="Ej: 10.0 FP 2111"></td></tr>
        <tr><th scope="row">Usar verificación SSL</th><td><label><input type="checkbox" name="sapwc_ssl" value="1" ' . checked(!empty($active['ssl']), true, false) . '> Sí</label></td></tr>
    </table>';
        submit_button('Guardar credenciales', 'primary', 'sapwc_save_credentials');
        echo '</form>';

        echo '<h2>Probar conexión</h2>';
        echo '<button id="sapwc-test-conn" class="button">Probar conexión</button>';
        echo '<p id="sapwc-test-result" style="font-weight: bold;"></p>';
       
$conn = sapwc_get_active_connection();
$version_info = null;

if ($conn) {
    $client = new SAPWC_API_Client($conn['url']);
    
    $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    $version_info = $client->get_version_info();
}

if ($version_info): ?>
    <h2>ℹ️ Información de SAP</h2>
    <table class="form-table">
        <tr>
            <th scope="row">Versión de Service Layer</th>
            <td><?php echo esc_html($version_info['ServiceLayerVersion']); ?></td>
        </tr>
        <tr>
            <th scope="row">Versión de SAP B1</th>
            <td><?php echo esc_html($version_info['SAPB1Version']); ?></td>
        </tr>
        <tr>
            <th scope="row">Base de Datos</th>
            <td><?php echo esc_html($version_info['CompanyDB']); ?></td>
        </tr>
        <tr>
            <th scope="row">Tipo de BD</th>
            <td><?php echo esc_html($version_info['DatabaseType']); ?></td>
        </tr>
    </table>
<?php elseif ($conn): ?>
    <p style="color: red; font-weight: bold;">❌ No se pudo obtener la versión de SAP.</p>
<?php endif; ?>
<?php
        echo '<hr>';
        echo '<h2>ℹ️ Compatibilidad</h2>';
        echo '<p>Este plugin ha sido probado y es compatible con:</p>';
        echo '<ul style="margin-left:1.5em;">
            <li><strong>SAP Business One 10.0 FP 2111</strong> (recomendado)</li>
            <li><strong>SAP Business One 9.3 PL14</strong> (funcionalidad limitada)</li>
            <li>Requiere servicio <code>/b1s/v1</code> disponible vía HTTPS</li>
          </ul>';

        echo '</div>';
    }
}
