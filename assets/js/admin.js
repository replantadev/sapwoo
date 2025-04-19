jQuery(document).ready(function ($) {

    $('#sapwc-test-conn').on('click', function (e) {

        e.preventDefault();

        var btn = $(this);

        var result = $('#sapwc-test-result');

        result.text('Comprobando conexión...');



        $.ajax({
            url: sapwc_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'sapwc_test_connection',
                nonce: sapwc_ajax.nonce
            },success: function (response) {
                if (response.success) {
                    result.css('color', 'green').text(response.data);
                } else {
                    let msg = (response.data && typeof response.data === 'object' && response.data.message)
                        ? response.data.message
                        : response.data || '❌ Error desconocido.';
                    result.css('color', 'red').text(msg);
                }
            }
            ,

            error: function () {

                result.css('color', 'red').text('Error AJAX');

            }

        });

    });

    $('#sapwc-send-orders').on('click', function (e) {
        e.preventDefault();
        let result = $('#sapwc-send-result');
        result.text('Enviando pedidos...').css('color', '#333');

        $('#sapwc-orders-table tbody tr').each(function () {
            let $row = $(this);
            let orderId = $row.data('order-id');
            let $statusCell = $row.find('.sapwc-status');
            let $button = $row.find('.sapwc-send-single');

            $button.prop('disabled', true).text('⌛ Enviando...');

            $.post(sapwc_ajax.ajax_url, {
                action: 'sapwc_send_order',
                nonce: sapwc_ajax.nonce,
                order_id: orderId
            }, function (response) {
                if (response.success) {
                    let docentry = response.data.docentry || 'N/A';
                    $statusCell.text('✅ Enviado (#' + docentry + ')');
                    $button.text('✅ Ok').removeClass('button-primary').addClass('button-success');
                } else {
                    $statusCell.text('❌ Error');
                    $button.text('❌ Reintentar').removeClass('button-primary').addClass('button-danger');
                }
            });
        });
    });

    $('.sapwc-send-single').on('click', function (e) {
        e.preventDefault();

        let $button = $(this);
        let orderId = $button.data('id');
        let $row = $button.closest('tr');
        let $statusCell = $row.find('.sapwc-status');

        $button.prop('disabled', true).text('⌛ Enviando...');

        $.post(sapwc_ajax.ajax_url, {
            action: 'sapwc_send_order',
            nonce: sapwc_ajax.nonce,
            order_id: orderId
        }, function (response) {

            if (!response.success && response.data && response.data.message === 'Pedido ya fue enviado a SAP') {
                $statusCell.html('✅ Ya enviado');
                $button.text('✅ Ok')
                    .removeClass('button-primary button-danger')
                    .addClass('button-success')
                    .prop('disabled', true);
                return;
            }

            if (response.success) {
                let docentry = response.data.docentry || '-';
                $statusCell.html('✅ Enviado (#' + docentry + ')');
                $button.text('✅ Ok')
                    .removeClass('button-primary button-danger')
                    .addClass('button-success');
                // Actualizar resumen si existe
                if (response.data.last_sync) {
                    $('td:contains("Última sincronización")').next('td, strong').html('<strong>' + response.data.last_sync + '</strong>');
                }
                if (response.data.docentry) {
                    $('td:contains("Último DocEntry")').next('td, strong').html('<strong>' + response.data.docentry + '</strong>');
                }

                $('<div class="notice notice-success is-dismissible"><p>' + msg + '</p></div>')
                    .insertAfter('.wrap h1').delay(2000).fadeOut();
            } else {
                $statusCell.html('❌ Error');
                $button.text('❌ Reintentar')
                    .removeClass('button-primary button-success')
                    .addClass('button-danger')
                    .prop('disabled', false);
            }
        }).fail(function () {
            $statusCell.html('❌ Error de red');
            $button.text('❌ Reintentar')
                .removeClass('button-primary button-success')
                .addClass('button-danger')
                .prop('disabled', false);
        });
    });

    const $trigger = $('#wp-admin-bar-sapwc_status_action_sync');

    $trigger.on('click', function (e) {
        e.preventDefault();
        if ($trigger.hasClass('loading')) return;

        const $text = $('#sapwc-sync-trigger');
        const originalText = $text.text();

        $trigger.addClass('loading');
        $text.text('Sincronizando...');

        $.post(ajaxurl, {
            action: 'sapwc_send_orders',
            nonce: sapwc_ajax.nonce
        }).done(function (res) {
            const message = res.success ? '✅ Pedidos sincronizados correctamente' : '❌ Error: ' + res.data;
            showToast(message, res.success ? 'success' : 'error');
            if (res.success && res.data.last_sync && res.data.last_docentry) {
                $('#sapwc-last-sync').text(res.data.last_sync);
                $('#sapwc-last-docentry').text(res.data.last_docentry);
            }
        }).fail(function () {
            showToast('❌ Error de red al sincronizar', 'error');
        }).always(function () {
            $trigger.removeClass('loading');
            $text.text(originalText);
        });

        function showToast(msg, type) {
            const color = type === 'success' ? '#46b450' : '#dc3232';
            const toast = $('<div>').css({
                position: 'fixed',
                top: '50px',
                right: '30px',
                background: color,
                color: '#fff',
                padding: '12px 20px',
                borderRadius: '4px',
                zIndex: 99999,
                boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
                fontSize: '14px'
            }).text(msg);

            $('body').append(toast);
            setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
        }
    });

    $('#sapwc-sync-orders').on('click', function () {
        $.post(ajaxurl, {
            action: 'sapwc_send_orders',
            nonce: sapwc_ajax.nonce
        }, function (response) {
            if (response.success) {
                $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                    .insertAfter('.wrap h1').delay(2000).fadeOut();

                // Actualiza los valores en la interfaz si existen
                if (response.data.last_sync) {
                    $('td:contains("Última sincronización")').next('td, strong').html('<strong>' + response.data.last_sync + '</strong>');
                }
            } else {
                alert('❌ Error al sincronizar: ' + response.data);
            }
        });
    });



    //Pedidos fallidos:
    $('.sapwc-retry-order').on('click', function() {
        const id = $(this).data('id');
        if (!confirm('¿Reintentar envío del pedido #' + id + '?')) return;

        $.post(ajaxurl, {
            action: 'sapwc_retry_failed_order',
            nonce: sapwc_ajax.nonce,
            order_id: id
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        });
    });

    $('.sapwc-remove-order').on('click', function() {
        const id = $(this).data('id');
        if (!confirm('¿Eliminar pedido fallido #' + id + '?')) return;

        $.post(ajaxurl, {
            action: 'sapwc_remove_failed_order',
            nonce: sapwc_ajax.nonce,
            order_id: id
        }, function() {
            location.reload();
        });
    });


});

