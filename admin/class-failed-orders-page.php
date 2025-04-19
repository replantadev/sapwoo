<?php

class SAPWC_Failed_Orders_Page {

    public static function render() {
        $failed = get_option('sapwc_failed_orders', []);

        echo '<div class="wrap"><h1>❌ Pedidos Fallidos SAP</h1>';
        echo '<p>Estos pedidos no se pudieron enviar a SAP. Puedes reintentarlo desde aquí.</p>';

        if (empty($failed)) {
            echo '<p><em>No hay pedidos fallidos registrados.</em></p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Razón del fallo</th>
                <th>Acción</th>
            </tr>
        </thead><tbody>';

        foreach ($failed as $order_id => $info) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            $name = $order->get_formatted_billing_full_name();
            $date = $info['timestamp'];
            $reason = esc_html($info['reason']);

            echo "<tr data-order-id='{$order_id}'>
                <td><a href='" . esc_url(get_edit_post_link($order_id)) . "'>#$order_id</a></td>
                <td>{$name}</td>
                <td>{$date}</td>
                <td><code>{$reason}</code></td>
                <td><button class='button button-primary sapwc-retry-order'>🔁 Reintentar</button></td>
            </tr>";
        }

        echo '</tbody></table></div>';

        self::inline_js();
    }

    private static function inline_js() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.sapwc-retry-order').on('click', function() {
                const $btn = $(this);
                const $row = $btn.closest('tr');
                const orderId = $row.data('order-id');

                $btn.prop('disabled', true).text('⌛ Enviando...');

                $.post(ajaxurl, {
                    action: 'sapwc_retry_failed_order',
                    nonce: sapwc_ajax.nonce,
                    order_id: orderId
                }, function(response) {
                    if (response.success) {
                        $btn.text('✅ Enviado').removeClass('button-primary').addClass('button-success');
                        $row.find('td:nth-child(4)').text('');
                    } else {
                        $btn.text('❌ Reintentar').prop('disabled', false).addClass('button-danger');
                        alert('Error: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
