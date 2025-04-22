<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SAPWC_Logs_Page
{
    public static function render()
    {
        global $wpdb;

        // Si se hizo clic en el botón de test, insertar un log de prueba
        if (isset($_GET['sapwc_test_log'])) {
            SAPWC_Logger::log(0, 'test', 'info', '🧪 Log de prueba manual desde la interfaz.');
            echo '<div class="notice notice-success"><p>✅ Log de prueba insertado correctamente.</p></div>';
        }

        $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sapwc_logs ORDER BY created_at DESC LIMIT 100");

        echo '<div class="wrap"><h1>📋 Registro de Logs SAP</h1>';

        echo '<p><button class="button button-secondary" id="sapwc-insert-test-log">🧪 Insertar Log de Prueba</button></p>
<div id="sapwc-test-log-message"></div>
';

        echo '<table class="widefat striped"><thead><tr>
            <th>Fecha</th>
            <th>Pedido</th>
            <th>Acción</th>
            <th>Estado</th>
            <th>Mensaje</th>
            <th>DocEntry</th>
        </tr></thead><tbody>';

        foreach ($logs as $log) {
            echo "<tr>
                <td>{$log->created_at}</td>
                <td>{$log->order_id}</td>
                <td>{$log->action}</td>
                <td>{$log->status}</td>
                <td>" . esc_html($log->message) . "</td>
                <td>" . esc_html($log->docentry) . "</td>
            </tr>";
        }

        echo '</tbody></table></div>';

        // Agregar el script de manera adecuada
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#sapwc-insert-test-log').on('click', function() {
                $.post(ajaxurl, {
                    action: 'sapwc_insert_test_log',
                    nonce: sapwc_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $('#sapwc-test-log-message').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                        setTimeout(() => location.reload(), 1500); // recarga para ver el log nuevo
                    } else {
                        $('#sapwc-test-log-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

add_action('wp_ajax_sapwc_insert_test_log', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!class_exists('SAPWC_Logger')) {
        wp_send_json_error('Logger no está cargado.');
    }

    SAPWC_Logger::log(null, 'test', 'info', '🧪 Log de prueba manual (AJAX)');
    wp_send_json_success('✅ Log de prueba insertado.');
});
