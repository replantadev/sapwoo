<?php

class SAPWC_Logger
{
    public static function log($order_id, $action, $status, $message, $docentry = null)
    {
        global $wpdb;

        $result = $wpdb->insert($wpdb->prefix . 'sapwc_logs', [
            'order_id' => is_numeric($order_id) ? (int)$order_id : 0,
            'action'    => $action,
            'status'    => $status,
            'message'   => $message,
            'docentry' => is_numeric($docentry) ? (int)$docentry : null,
            'created_at' => wp_date('Y-m-d H:i:s')

        ]);

        if ($result === false) {
            error_log('[SAPWC_LOGGER] ❌ Error al insertar log: ' . $wpdb->last_error);
            error_log('[SAPWC_LOGGER] Última query: ' . $wpdb->last_query);
        }
    }
}
