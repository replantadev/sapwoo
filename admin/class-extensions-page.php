<?php
class SAPWC_Extensions_Page
{
    public static function get_extensions()
    {
        return [
            'logistica' => [
                'label' => '📦 Logística & Envíos',
                'description' => 'Mapea métodos de envío, ruta, portes y más.',
                'fields' => [
                    'shipping_method' => 'TransportationCode',
                    'tracking_number' => 'U_ARTES_NumTracking',
                    'portes' => 'U_ARTES_Portes',
                    'ruta' => 'U_ARTES_Ruta'
                ]
            ],
            'estado_pedido' => [
                'label' => '🔄 Estado Avanzado',
                'description' => 'Sincroniza estados personalizados y devoluciones.',
                'fields' => [
                    'status' => 'U_ARTES_EST',
                    'devuelto' => 'U_ARTES_ESTDEV'
                ]
            ],
            'etiquetas' => [
                'label' => '🏷️ Etiquetas e impresión',
                'description' => 'Envía flags para impresión automática desde SAP.',
                'fields' => [
                    'imprimir_etiqueta' => 'U_ARTES_ImpEti',
                    'impresion_albaran' => 'U_ARTES_ImpAlb'
                ]
            ]
        ];
    }

    public static function render()
    {
        $extensions = self::get_extensions();
        $mapping_ext = get_option('sapwc_field_mapping_extensiones', []);
        $active_ext = get_option('sapwc_active_extensions', []);

        echo '<div class="wrap"><h1>⚙️ Extensiones SAP Woo</h1>';
        echo '<form method="post">';
        echo '<input type="hidden" name="sapwc_extensions_save" value="1">';

        foreach ($extensions as $slug => $ext) {
            $is_active = in_array($slug, $active_ext);
            echo '<div class="postbox" style="padding:1em;margin-bottom:2em;">';
            echo '<h2>' . $ext['label'] . ' <small>(' . esc_html($slug) . ')</small></h2>';
            echo '<p>' . $ext['description'] . '</p>';
            echo '<label><input type="checkbox" name="sapwc_active_extensions[]" value="' . esc_attr($slug) . '" ' . checked($is_active, true, false) . '> Activar esta extensión</label>';
            echo '<table class="widefat fixed striped" style="margin-top:1em;"><thead><tr><th>Campo WooCommerce</th><th>Campo SAP</th></tr></thead><tbody>';

            foreach ($ext['fields'] as $woo => $default_sap) {
                $current = $mapping_ext[$slug][$woo] ?? $default_sap;
                echo '<tr>';
                echo '<td>' . esc_html($woo) . '</td>';
                echo '<td><input type="text" name="sapwc_field_mapping_extensiones[' . esc_attr($slug) . '][' . esc_attr($woo) . ']" value="' . esc_attr($current) . '" class="regular-text" list="sap-suggestions"></td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '<datalist id="sap-suggestions">';
        foreach (['ItemCode', 'TransportationCode', 'U_ARTES_EST', 'U_ARTES_Palets', 'U_ARTES_Ruta'] as $s) {
            echo '<option value="' . esc_attr($s) . '">';
        }
        echo '</datalist>';

        submit_button('Guardar configuración');
        echo '</form></div>';

        // Lógica de guardado
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sapwc_extensions_save'])) {
            $mapeo = $_POST['sapwc_field_mapping_extensiones'] ?? [];
            $activos = $_POST['sapwc_active_extensions'] ?? [];

            update_option('sapwc_field_mapping_extensiones', array_map('sanitize_text_field_deep', $mapeo));
            update_option('sapwc_active_extensions', array_map('sanitize_text_field', $activos));

            echo '<div class="updated"><p>✅ Extensiones y mapeo guardados.</p></div>';
        }
    }

    public static function sanitize_text_field_deep($data)
    {
        if (is_array($data)) {
            return array_map('self::sanitize_text_field_deep', $data);
        }
        return sanitize_text_field($data);
    }
}