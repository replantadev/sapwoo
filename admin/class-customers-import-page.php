<?php
if (!defined('ABSPATH')) {
    exit;
}
// Evitar carga directa del archivo
if (!defined('SAPWC_PLUGIN_PATH')) {
    define('SAPWC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
require_once SAPWC_PLUGIN_PATH . 'includes/helper.php';

class SAPWC_Customers_Import_Page
{
    public static function render()
    {
        $mode = get_option('sapwc_mode', 'ecommerce');
        $filter_type = get_option('sapwc_customer_filter_type', 'starts');
        $filter_value = sanitize_text_field(trim(get_option('sapwc_customer_filter_value', '')));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Importar Clientes desde SAP', 'sapwoo') . '</h1>';
        echo '<p>' . esc_html__('Modo actual: ', 'sapwoo') . '<strong>' . esc_html($mode) . '</strong></p>';
        echo '<p>' . esc_html__('Filtro aplicado: ', 'sapwoo') . '<strong>' . esc_html($filter_type . ' → ' . $filter_value) . '</strong></p>';

        echo '<button id="sapwc-import-all-customers" class="button button-primary">📥 Importar todos los clientes</button>';
        echo '<p id="sapwc-import-result" style="margin-top: 1em;"></p>';
        echo '<input type="text" id="custom-sap-search" placeholder="🔎 Buscar cliente..." style="margin-bottom:10px;padding:6px;width:100%;max-width:300px;"><span class="button reload" style="margin-left: 10px;" id="sapwc-customers-table-button">🔄 Recargar</span>';

        echo '<table id="sapwc-customers-table" class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('CardCode', 'sapwoo') . '</th>';
        echo '<th>' . esc_html__('Nombre', 'sapwoo') . '</th>';
        echo '<th>' . esc_html__('CIF', 'sapwoo') . '</th>';

        echo '<th>' . esc_html__('Email', 'sapwoo') . '</th>';
        echo '<th>' . esc_html__('Dirección', 'sapwoo') . '</th>';
        //echo '<th>' . esc_html__('Dirección de Entrega', 'sapwoo') . '</th>';
        echo '<th>' . esc_html__('Estado', 'sapwoo') . '</th>';

        echo '<th>' . esc_html__('Acción', 'sapwoo') . '</th>';
        echo '</tr></thead>';
        echo '<tbody></tbody>';
        echo '</table>';

        self::inline_js();

        echo '</div>';
    }

    private static function inline_js()
    {
?>
        <script>
            jQuery(document).ready(function($) {

                // Inicializar DataTables
                $('#sapwc-customers-table').DataTable({
                    serverSide: true,
                    processing: true,
                    searching: false, // ocultamos el buscador original
                    ajax: {
                        url: sapwc_ajax.ajax_url,
                        type: 'POST',
                        data: function(d) {
                            d.action = 'sapwc_get_sap_customers_dt';
                            d.nonce = sapwc_ajax.nonce;
                            d.search_value = $('#custom-sap-search').val(); // <-- esto sí está bien
                            return d;
                        }
                    },
                    columns: [{
                            data: 'CardCode'
                        },
                        {
                            data: 'CardName',
                            render: function(data, type, row) {
                                return data; // ya viene preformateado del backend
                            }
                        }, 
                        {
                            data: 'FederalTaxID'
                        },
                        {
                            data: 'EmailAddress'
                        },
                        {
                            data: 'Address'
                        },
                        {
                            data: 'is_imported',
                            render: function(isImported) {
                                return isImported ? '✅ Importado' : '–';
                            },
                            orderable: false,
                            searchable: false
                        },

                        /*{
                            data: 'BPAddresses',
                            render: function(data) {
                                if (Array.isArray(data)) {
                                    const shipTo = data.find(addr => addr.AddressType === 'bo_ShipTo');
                                    return shipTo?.Address || data[0]?.Address || '';
                                }
                                return '';
                            },
                            orderable: false,
                            searchable: false
                        },*/
                        {
                            data: 'CardCode',
                            render: function(data) {
                                return `<button class="button sapwc-import-customer" data-code="${$('<div>').text(data).html()}"
>📥 Importar</button>`;
                            },
                            orderable: false,
                            searchable: false
                        }
                    ],
                    pageLength: 10,
                    language: {
                        processing: "Cargando...",
                        lengthMenu: "Mostrar _MENU_ clientes",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ clientes",
                        paginate: {
                            first: "Primero",
                            last: "Último",
                            next: "Siguiente",
                            previous: "Anterior"
                        }
                    }
                });
                // Buscador personalizado
                $('#custom-sap-search').on('change', function() {
                    const value = $(this).val();

                    $('#sapwc-customers-table').DataTable().ajax.reload();
                });
                // Limpiar el buscador al cargar la página
                $('#custom-sap-search').val('');
                // Botón importar individual
                $(document).on('click', '.sapwc-import-customer', function() {
                    const $btn = $(this);
                    const cardCode = $btn.data('code');

                    $btn.prop('disabled', true).text('⏳ Importando...');

                    $.post(sapwc_ajax.ajax_url, {
                        action: 'sapwc_import_single_customer',
                        nonce: sapwc_ajax.nonce,
                        cardcode: cardCode
                    }, function(res) {
                        if (res.success) {
                            $btn.replaceWith('✅ Importado');
                        } else {
                            $btn.prop('disabled', false).text('📥 Importar');
                            alert('❌ Error: ' + res.data);
                        }
                    });
                });
                //recargar tabla desde botón recargar
                $('#sapwc-customers-table-button').on('click', '.reload', function() {
                    //recargar tabla desde inicio
                    $('#custom-sap-search').val('');
                    $('#sapwc-customers-table').DataTable().ajax.reload();
                });

                // Botón importar todos
                $('#sapwc-import-all-customers').on('click', function() {
                    const $btn = $(this);
                    $btn.prop('disabled', true).text('⏳ Importando todos...');

                    $.post(sapwc_ajax.ajax_url, {
                        action: 'sapwc_import_all_customers',
                        nonce: sapwc_ajax.nonce
                    }, function(res) {
                        $('#sapwc-import-result').html(`<p><strong>${res.success ? '✅ ' : '❌ '}${res.data.message}</strong></p>`);
                        table.ajax.reload(); // recargar la tabla después de importar
                    }).fail(function() {
                        $('#sapwc-import-result').html('<p><strong>❌ Error de red al importar clientes.</strong></p>');
                    }).always(function() {
                        $btn.prop('disabled', false).text('📥 Importar todos los clientes');
                    });
                });

            });
        </script>
<?php
    }
}

add_action('wp_ajax_sapwc_get_sap_customers', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(__('No hay conexión activa.', 'sapwoo'));
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error($login['message']);
    }

    $filter_type  = get_option('sapwc_customer_filter_type', 'starts');
    $filter_value = sanitize_text_field(get_option('sapwc_customer_filter_value', ''));

    $skip = isset($_POST['skip']) ? intval($_POST['skip']) : 0;

    $base_query = "/BusinessPartners?\$filter=startswith(CardCode,'$filter_value')&\$orderby=CardCode&\$select=CardCode,CardName,FederalTaxID,EmailAddress,Phone1,Address,BPAddresses,City,ZipCode,State&\$top=20&\$skip=$skip";

    $response = $client->get($base_query);

    if (!isset($response['value'])) {
        wp_send_json_error(__('No se pudieron obtener los clientes.', 'sapwoo'));
    }

    wp_send_json_success([
        'customers' => $response['value'],
        'nextLink' => isset($response['odata.nextLink']) ? $response['odata.nextLink'] : null,
        'nextSkip' => $skip + 20,
    ]);
});



add_action('wp_ajax_sapwc_import_single_customer', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $cardcode = sanitize_text_field($_POST['cardcode']);
    if (!$cardcode) {
        wp_send_json_error('CardCode faltante.');
    }

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error('No hay conexión activa con SAP.');
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    if (!$login['success']) {
        wp_send_json_error('Error al conectar con SAP: ' . $login['message']);
    }

    $response = $client->get("/BusinessPartners('$cardcode')");
    if (!isset($response['CardCode'])) {
        wp_send_json_error('Cliente no encontrado en SAP.');
    }

    // Parsear nombre y apellidos desde CardName
    $full_name = $response['CardName'] ?? $cardcode;
    $first_name = '';
    $last_name = '';

    if (strpos($full_name, ',') !== false) {
        [$last_name, $first_name] = array_map('trim', explode(',', $full_name, 2));
    } else {
        $first_name = $full_name;
    }

    // Usar nombre comercial si existe
    $display_name = $response['CardForeignName'] ?? trim("$first_name $last_name");

    $user_id = username_exists($cardcode);
    if (!$user_id) {
        $user_id = wp_insert_user([
            'user_login'   => $cardcode,
            'user_pass'    => wp_generate_password(),
            'user_email'   => sanitize_email($response['EmailAddress'] ?? ''),
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $display_name,
        ]);
    }

    if (is_wp_error($user_id)) {
        wp_send_json_error('Error al crear el usuario: ' . $user_id->get_error_message());
    }


    // Guardar campos adicionales
    update_user_meta($user_id, 'sapwc_cardcode', $cardcode);
    update_user_meta($user_id, 'billing_company', $response['CardName'] ?? '');
    update_user_meta($user_id, 'billing_email', sanitize_email($response['EmailAddress'] ?? ''));
    update_user_meta($user_id, 'nif', $response['FederalTaxID'] ?? '');
    update_user_meta($user_id, 'company_name', $response['CardForeignName'] ?? '');
    update_user_meta($user_id, 'billing_address_1', $response['Address'] ?? '');
    update_user_meta($user_id, 'billing_city', $response['City'] ?? '');
    update_user_meta($user_id, 'billing_postcode', $response['ZipCode'] ?? '');
    update_user_meta($user_id, 'billing_state', get_valid_wc_state($response['State'] ?? ''));
    update_user_meta($user_id, 'billing_country', 'ES'); // Asignar España como país por defecto
    update_user_meta($user_id, 'billing_phone', sanitize_text_field($response['Phone1'] ?? ''));
    if (!empty($response['BPAddresses']) && is_array($response['BPAddresses'])) {
        foreach ($response['BPAddresses'] as $bp_addr) {
            if ($bp_addr['AddressType'] === 'bo_ShipTo') {
                update_user_meta($user_id, 'shipping_address_1', $bp_addr['Address'] ?? '');
                update_user_meta($user_id, 'shipping_city', $bp_addr['City'] ?? '');
                update_user_meta($user_id, 'shipping_postcode', $bp_addr['ZipCode'] ?? '');
                update_user_meta($user_id, 'shipping_state', get_valid_wc_state($bp_addr['State'] ?? ''));
                break;
            }
        }
    }
    // Asignar rol de cliente
    $user = new WP_User($user_id);
    $user->set_role('customer');

    wp_send_json_success('Cliente importado correctamente.');
});

add_action('wp_ajax_sapwc_import_all_customers', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    // Reutilizamos la lógica del endpoint anterior
    $_POST['nonce'] = wp_create_nonce('sapwc_nonce');

    ob_start();
    do_action('wp_ajax_sapwc_get_sap_customers');
    $output = ob_get_clean();

    $decoded = json_decode($output, true);
    if (!$decoded || !isset($decoded['data'])) {
        wp_send_json_error(['message' => 'No se pudo recuperar la lista de clientes.']);
    }

    $imported = 0;
    foreach ($decoded['data'] as $c) {
        $_POST['cardcode'] = $c['CardCode'];
        ob_start();
        do_action('wp_ajax_sapwc_import_single_customer');
        $r = json_decode(ob_get_clean(), true);
        if (!empty($r['success'])) {
            $imported++;
        }
    }

    wp_send_json_success(['message' => "Se importaron $imported clientes."]);
});


add_action('wp_ajax_sapwc_get_sap_customers_dt', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json([
            'draw' => intval($_POST['draw'] ?? 0),
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0
        ]);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);
    if (!$login['success']) {
        wp_send_json([
            'draw' => intval($_POST['draw'] ?? 0),
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0
        ]);
    }

    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $search = sanitize_text_field($_POST['search_value'] ?? '');

    $filter_query = "";
    if ($search !== '') {
        $filter_query = "\$filter=contains(CardName,'$search') or contains(CardCode,'$search') or contains(EmailAddress,'$search')";
    } else {
        $filter_value = sanitize_text_field(get_option('sapwc_customer_filter_value', ''));
        $filter_query = "\$filter=startswith(CardCode,'$filter_value')";
    }

    $select = 'CardCode,CardName,CardForeignName,FederalTaxID,EmailAddress,Address,City,ZipCode,Phone1';
    $query = "/BusinessPartners?$filter_query&\$orderby=CardCode&\$select=$select&\$top=$length&\$skip=$start";

    $response = $client->get($query);

    if (!isset($response['value'])) {
        wp_send_json([
            'draw' => intval($_POST['draw'] ?? 0),
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0
        ]);
    }

    $mode = get_option('sapwc_mode', 'ecommerce');

    foreach ($response['value'] as &$row) {
        // Dirección completa: Dirección, CP, Ciudad
        $address = trim($row['Address'] ?? '');
        $zip     = trim($row['ZipCode'] ?? '');
        $city    = trim($row['City'] ?? '');
        $phone   = trim($row['Phone1'] ?? '');

        $full_address = implode(', ', array_filter([$address, $zip, $city]));
        $row['Address'] = $full_address;
        if (!empty($phone)) {
            $row['Address'] .= "<br><small>📞 " . esc_html($phone) . "</small>";
        }
        // Nombre combinado para mostrar en DataTable
        $card_name = '<strong>' . esc_html($row['CardName'] ?? '') . '</strong>';
        $foreign_name = trim($row['CardForeignName'] ?? '');

        if (!empty($foreign_name)) {
            $row['CardName'] ='<em>'. esc_html($foreign_name) . '</em><br>' . $card_name;
        } else {
            $row['CardName'] = $card_name;
        }


        // Comprobación de importación
        if ($mode === 'b2b') {
            $b2b_meta_key = get_option('sapwc_b2b_cardcode_meta', 'user_login');

            if ($b2b_meta_key === 'user_login') {
                $row['is_imported'] = username_exists($row['CardCode']) ? true : false;
            } else {
                $users = get_users([
                    'meta_key'   => $b2b_meta_key,
                    'meta_value' => $row['CardCode'],
                    'number'     => 1,
                    'fields'     => 'ID'
                ]);
                $row['is_imported'] = !empty($users);
            }
        } else {
            $row['is_imported'] = username_exists($row['CardCode']) ? true : false;
        }
    }

    wp_send_json([
        'draw' => intval($_POST['draw'] ?? 0),
        'data' => $response['value'],
        'recordsTotal' => $start + count($response['value']) + 1,
        'recordsFiltered' => $start + count($response['value']) + 1
    ]);
});
