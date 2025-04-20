<?php
class SAPWC_UDF_Mapping_Page
{
    public static function render()
    {
        $mapping = get_option('sapwc_udf_mapping', []);
        $conn = sapwc_get_active_connection();
        $fields = [];

        if ($conn) {
            $client = new SAPWC_API_Client($conn['url']);
            $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
            if ($login['success']) {
                $metadata = $client->get_metadata(); // crea este wrapper si no existe
                $fields = SAPWC_UDFields_Helper::get_udf_fields_from_metadata($metadata);
            }
        }

        echo '<div class="wrap"><h1>🧩 Campos Personalizados U_</h1>';
        echo '<form method="post"><input type="hidden" name="sapwc_udf_save" value="1">';
        echo '<table class="widefat fixed striped"><thead><tr><th>Campo SAP (U_)</th><th>Campo Woo (meta u otro)</th></tr></thead><tbody>';

        foreach ($fields as $field) {
            $val = esc_attr($mapping[$field] ?? '');
            echo '<tr>';
            echo '<td><code>' . esc_html($field) . '</code></td>';
            echo '<td><input type="text" name="sapwc_udf_mapping[' . esc_attr($field) . ']" value="' . $val . '" class="regular-text"></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        submit_button('Guardar mapeo');
        echo '</form></div>';

        // Save logic
        if (isset($_POST['sapwc_udf_save'])) {
            update_option('sapwc_udf_mapping', array_map('sanitize_text_field', $_POST['sapwc_udf_mapping']));
            echo '<div class="updated"><p>✅ Mapeo guardado correctamente.</p></div>';
        }
    }
}
