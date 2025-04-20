<?php

class SAPWC_Logger {
    public static function log($order_id, $action, $status, $message, $docentry = null) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'sapwc_logs', [
            'order_id'  => $order_id,
            'action'    => $action,        // Ej: 'sync', 'patch', 'error', etc.
            'status'    => $status,        // 'success', 'warning', 'error'
            'message'   => $message,
            'docentry'  => $docentry,
            'created_at'=> current_time('mysql')
        ]);
    }
}

    
      

