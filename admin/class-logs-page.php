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

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=sapwc-logs&sapwc_test_log=1')) . '" class="button button-primary">🧪 Insertar Log de Prueba</a></p>';

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
    }
}
