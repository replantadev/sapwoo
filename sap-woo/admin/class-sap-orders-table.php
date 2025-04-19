<?php
// Archivo: admin/class-sap-orders-table.php

class SAPWC_SAP_Orders_Table
{

    public static function render_table_block()
    {
        echo '<h2 style="margin-top: 3em;">📝 Últimos pedidos en SAP (Clientes Web) <span id="sapwc-status-indicator" style="margin-left: 10px;">🔴</span></h2>';

        echo '<table class="widefat striped" id="sapwc-sap-orders-table" style="margin-top: 1em; width:100%">';
        echo '<thead><tr>
        <th>DocEntry</th>
        <th>DocNum</th>
        <th>Fecha</th>
        <th>Cliente</th>
        <th>Total</th>
        <th>Comentarios</th>
    </tr></thead><tbody></tbody></table>';
        self::inline_js(); // esto es vital
    }


    private static function inline_js()
    {
?>
        <script>
            jQuery(document).ready(function($) {
                // Inicializa el botón de búsqueda y el campo de texto

                function refreshSAPOrders(searchValue = '') {
                    $('#sapwc-status-indicator').text('🟠');
                    const $table = $('#sapwc-sap-orders-table');

                    $table.find('tbody').html('<tr><td colspan="6">Cargando pedidos desde SAP...</td></tr>');

                    let data = {
                        action: 'sapwc_get_sap_orders',
                        nonce: sapwc_ajax.nonce
                    };

                    if (searchValue) {
                        data.search = searchValue;
                    }

                    $.ajax({
                        url: sapwc_ajax.ajax_url,
                        method: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success) {
                                $('#sapwc-status-indicator').text('🟢');

                                const rows = response.data
                                    .filter(order => order.DocEntry && order.DocDate && order.DocNum) // seguridad extra
                                    .map(order => {
                                        const [year, month, day] = order.DocDate.split('-');
                                        const formattedDate = `${day}/${month}/${year}`;
                                        return `<tr>
                            <td>${order.DocEntry}</td>
                            <td>${order.DocNum}</td>
                            <td>${formattedDate}</td>
                            <td>${order.CardCode}</td>
                            <td>${parseFloat(order.DocTotal).toFixed(2)} €</td>
                            <td>${order.Comments || ''}</td>
                        </tr>`;
                                    }).join('');

                                $table.find('tbody').html(rows || '<tr><td colspan="6">No se encontraron resultados.</td></tr>');

                                // Destruir DataTable si ya existe
                                if ($.fn.DataTable.isDataTable($table)) {
                                    $table.DataTable().clear().destroy();
                                }

                                // Inicializar DataTables de nuevo
                                $table.DataTable({
                                    order: [
                                        [0, 'desc']
                                    ],
                                    pageLength: 10,
                                    language: {
                                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                                    }
                                });

                            } else {
                                $('#sapwc-status-indicator').text('🔴');
                                $table.find('tbody').html('<tr><td colspan="6">Error: ' + response.data + '</td></tr>');
                            }
                        },
                        error: function() {
                            $('#sapwc-status-indicator').text('🔴');
                            $table.find('tbody').html('<tr><td colspan="6">Error de red al contactar con SAP.</td></tr>');
                        }
                    });
                }


                $('#sapwc-refresh-sap-table').on('click', function() {
                    refreshSAPOrders(); // carga normal
                    location.reload();
                });

                $('#sapwc-search-btn').on('click', function() {
                    const searchVal = $('#sapwc-search-numatcard').val().trim();
                    if (searchVal) {
                        refreshSAPOrders(searchVal);
                    }
                });

                $('#sapwc-refresh-sap-table').on('click', function() {
                    refreshSAPOrders(); // Ya lo tienes
                    location.reload(); // Esto recarga la tabla superior de Woo
                });

                refreshSAPOrders();
            });
        </script>
<?php
    }
}

// Hook AJAX
add_action('wp_ajax_sapwc_get_sap_orders', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $conn = sapwc_get_active_connection();
    if (!$conn) {
        wp_send_json_error(['message' => '❌ No hay conexión activa con SAP.']);
    }

    $client = new SAPWC_API_Client($conn['url']);
    $login  = $client->login($conn['user'], $conn['pass'], $conn['db'], $conn['ssl'] ?? false);

    if (!$login['success']) {
        wp_send_json_error(['message' => '❌ Error al conectar con SAP: ' . $login['message']]);
    }

    $search = sanitize_text_field($_POST['search'] ?? '');

    if ($search) {
        $query = "/Orders?\$filter=NumAtCard eq '$search'&\$orderby=DocEntry desc&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
    } else {
        $query = "/Orders?\$filter=(CardCode eq 'WNAD PENINSULA' or CardCode eq 'WNAD CANARIAS')&\$orderby=DocEntry desc&\$top=50&\$select=DocEntry,DocNum,DocDate,CardCode,DocTotal,Comments";
    }

    $response = $client->get($query);

    if (!isset($response['value'])) {
        wp_send_json_error('Error al obtener los pedidos de SAP');
    }

    wp_send_json_success($response['value']);
    wp_die();
});
