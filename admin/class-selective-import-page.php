<?php
/**
 * Página de Importación Selectiva
 * 
 * Permite ver items de SAP no importados y seleccionar cuáles importar.
 * Incluye preview de campos antes de importar y progreso individual.
 *
 * @package SAPWC
 * @since 1.4.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAPWC_Selective_Import_Page
{
    public static function render()
    {
        $active_tab = sanitize_text_field($_GET['tab'] ?? 'products');
        $valid_tabs = ['products', 'categories', 'customers'];
        if (!in_array($active_tab, $valid_tabs)) {
            $active_tab = 'products';
        }

        ?>
        <div class="wrap sapwc-selective-import">
            <h1><span class="dashicons dashicons-filter"></span> <?php esc_html_e('Importación Selectiva', 'sapwoo'); ?></h1>
            <p class="description"><?php esc_html_e('Visualiza y selecciona qué elementos de SAP importar a WooCommerce. Preview de campos antes de importar.', 'sapwoo'); ?></p>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sapwc-selective-import&tab=products')); ?>"
                   class="nav-tab <?php echo $active_tab === 'products' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-products" style="font-family:dashicons;"></span> <?php esc_html_e('Productos', 'sapwoo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sapwc-selective-import&tab=categories')); ?>"
                   class="nav-tab <?php echo $active_tab === 'categories' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-category" style="font-family:dashicons;"></span> <?php esc_html_e('Categorías', 'sapwoo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sapwc-selective-import&tab=customers')); ?>"
                   class="nav-tab <?php echo $active_tab === 'customers' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-groups" style="font-family:dashicons;"></span> <?php esc_html_e('Clientes', 'sapwoo'); ?>
                </a>
            </nav>

            <div class="sapwc-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'products':
                        self::render_products_tab();
                        break;
                    case 'categories':
                        self::render_categories_tab();
                        break;
                    case 'customers':
                        self::render_customers_tab();
                        break;
                }
                ?>
            </div>

            <!-- Modal de Preview -->
            <div id="sapwc-preview-modal" class="sapwc-modal" style="display:none;">
                <div class="sapwc-modal-overlay"></div>
                <div class="sapwc-modal-content">
                    <div class="sapwc-modal-header">
                        <h2 id="sapwc-modal-title"><?php esc_html_e('Vista Previa de Importación', 'sapwoo'); ?></h2>
                        <button class="sapwc-modal-close" type="button">&times;</button>
                    </div>
                    <div class="sapwc-modal-body">
                        <div class="sapwc-preview-loading" style="display:none;">
                            <span class="spinner is-active"></span> <?php esc_html_e('Cargando datos...', 'sapwoo'); ?>
                        </div>
                        <div class="sapwc-preview-content">
                            <div class="sapwc-preview-columns">
                                <div class="sapwc-preview-sap">
                                    <h3><span class="dashicons dashicons-database"></span> <?php esc_html_e('Datos en SAP', 'sapwoo'); ?></h3>
                                    <table class="wp-list-table widefat striped" id="sapwc-preview-sap-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Campo', 'sapwoo'); ?></th>
                                                <th><?php esc_html_e('Valor', 'sapwoo'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="sapwc-preview-arrow">
                                    <span class="dashicons dashicons-arrow-right-alt"></span>
                                </div>
                                <div class="sapwc-preview-woo">
                                    <h3><span class="dashicons dashicons-wordpress"></span> <?php esc_html_e('Destino en WooCommerce', 'sapwoo'); ?></h3>
                                    <table class="wp-list-table widefat striped" id="sapwc-preview-woo-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Campo WooCommerce', 'sapwoo'); ?></th>
                                                <th><?php esc_html_e('Origen SAP', 'sapwoo'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="sapwc-preview-status" id="sapwc-preview-status"></div>
                    </div>
                    <div class="sapwc-modal-footer">
                        <button type="button" class="button" id="sapwc-modal-cancel"><?php esc_html_e('Cancelar', 'sapwoo'); ?></button>
                        <button type="button" class="button button-primary" id="sapwc-modal-import">
                            <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                            <?php esc_html_e('Importar Ahora', 'sapwoo'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php self::render_styles(); ?>
        <?php self::render_scripts(); ?>
        <?php
    }

    // =========================================================================
    // TAB: PRODUCTOS PENDIENTES
    // =========================================================================
    private static function render_products_tab()
    {
        ?>
        <div class="sapwc-selective-section">
            <div class="sapwc-section-header">
                <h2><?php esc_html_e('Productos de SAP Pendientes de Importar', 'sapwoo'); ?></h2>
                <div class="sapwc-section-actions">
                    <button id="sapwc-load-pending-products" class="button button-primary">
                        <span class="dashicons dashicons-update" style="font-family:dashicons;vertical-align:middle;"></span>
                        <?php esc_html_e('Cargar Productos Pendientes', 'sapwoo'); ?>
                    </button>
                    <button id="sapwc-import-selected-products" class="button" disabled>
                        <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                        <?php esc_html_e('Importar Seleccionados', 'sapwoo'); ?>
                        <span class="sapwc-selected-count">(0)</span>
                    </button>
                </div>
            </div>

            <div class="sapwc-loading-indicator" id="products-loading" style="display:none;">
                <span class="spinner is-active"></span> <?php esc_html_e('Consultando SAP...', 'sapwoo'); ?>
            </div>

            <div class="sapwc-results-info" id="products-results-info" style="display:none;">
                <span class="dashicons dashicons-info"></span>
                <span id="products-count-text"></span>
            </div>

            <table id="sapwc-pending-products-table" class="wp-list-table widefat fixed striped" style="display:none;">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="sapwc-select-all-products"></th>
                        <th><?php esc_html_e('Código', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Nombre', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Grupo', 'sapwoo'); ?></th>
                        <th style="width:180px;"><?php esc_html_e('Acciones', 'sapwoo'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <div class="sapwc-import-progress" id="products-import-progress" style="display:none;">
                <h4><?php esc_html_e('Progreso de Importación', 'sapwoo'); ?></h4>
                <div class="sapwc-progress-bar">
                    <div class="sapwc-progress-fill" id="products-progress-fill">0%</div>
                </div>
                <div class="sapwc-progress-log" id="products-import-log"></div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // TAB: CATEGORÍAS PENDIENTES
    // =========================================================================
    private static function render_categories_tab()
    {
        ?>
        <div class="sapwc-selective-section">
            <div class="sapwc-section-header">
                <h2><?php esc_html_e('Categorías de SAP Pendientes de Importar', 'sapwoo'); ?></h2>
                <div class="sapwc-section-actions">
                    <button id="sapwc-load-pending-categories" class="button button-primary">
                        <span class="dashicons dashicons-update" style="font-family:dashicons;vertical-align:middle;"></span>
                        <?php esc_html_e('Cargar Categorías Pendientes', 'sapwoo'); ?>
                    </button>
                    <button id="sapwc-import-selected-categories" class="button" disabled>
                        <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                        <?php esc_html_e('Importar Seleccionadas', 'sapwoo'); ?>
                        <span class="sapwc-selected-count">(0)</span>
                    </button>
                </div>
            </div>

            <div class="sapwc-loading-indicator" id="categories-loading" style="display:none;">
                <span class="spinner is-active"></span> <?php esc_html_e('Consultando SAP...', 'sapwoo'); ?>
            </div>

            <div class="sapwc-results-info" id="categories-results-info" style="display:none;">
                <span class="dashicons dashicons-info"></span>
                <span id="categories-count-text"></span>
            </div>

            <table id="sapwc-pending-categories-table" class="wp-list-table widefat fixed striped" style="display:none;">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="sapwc-select-all-categories"></th>
                        <th><?php esc_html_e('Nº Grupo', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Nombre', 'sapwoo'); ?></th>
                        <th style="width:180px;"><?php esc_html_e('Acciones', 'sapwoo'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <div class="sapwc-import-progress" id="categories-import-progress" style="display:none;">
                <h4><?php esc_html_e('Progreso de Importación', 'sapwoo'); ?></h4>
                <div class="sapwc-progress-bar">
                    <div class="sapwc-progress-fill" id="categories-progress-fill">0%</div>
                </div>
                <div class="sapwc-progress-log" id="categories-import-log"></div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // TAB: CLIENTES PENDIENTES
    // =========================================================================
    private static function render_customers_tab()
    {
        $mode = get_option('sapwc_mode', 'ecommerce');
        ?>
        <div class="sapwc-selective-section">
            <?php if ($mode !== 'b2b'): ?>
                <div class="notice notice-warning">
                    <p><span class="dashicons dashicons-warning"></span> 
                    <?php esc_html_e('La importación de clientes solo está disponible en modo B2B. Activa el modo B2B en Opciones de Sincronización.', 'sapwoo'); ?></p>
                </div>
            <?php else: ?>
            <div class="sapwc-section-header">
                <h2><?php esc_html_e('Clientes de SAP Pendientes de Importar', 'sapwoo'); ?></h2>
                <div class="sapwc-section-actions">
                    <button id="sapwc-load-pending-customers" class="button button-primary">
                        <span class="dashicons dashicons-update" style="font-family:dashicons;vertical-align:middle;"></span>
                        <?php esc_html_e('Cargar Clientes Pendientes', 'sapwoo'); ?>
                    </button>
                    <button id="sapwc-import-selected-customers" class="button" disabled>
                        <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                        <?php esc_html_e('Importar Seleccionados', 'sapwoo'); ?>
                        <span class="sapwc-selected-count">(0)</span>
                    </button>
                </div>
            </div>

            <div class="sapwc-loading-indicator" id="customers-loading" style="display:none;">
                <span class="spinner is-active"></span> <?php esc_html_e('Consultando SAP...', 'sapwoo'); ?>
            </div>

            <div class="sapwc-results-info" id="customers-results-info" style="display:none;">
                <span class="dashicons dashicons-info"></span>
                <span id="customers-count-text"></span>
            </div>

            <table id="sapwc-pending-customers-table" class="wp-list-table widefat fixed striped" style="display:none;">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="sapwc-select-all-customers"></th>
                        <th><?php esc_html_e('CardCode', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Nombre', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Email', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('NIF/CIF', 'sapwoo'); ?></th>
                        <th style="width:180px;"><?php esc_html_e('Acciones', 'sapwoo'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <div class="sapwc-import-progress" id="customers-import-progress" style="display:none;">
                <h4><?php esc_html_e('Progreso de Importación', 'sapwoo'); ?></h4>
                <div class="sapwc-progress-bar">
                    <div class="sapwc-progress-fill" id="customers-progress-fill">0%</div>
                </div>
                <div class="sapwc-progress-log" id="customers-import-log"></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // ESTILOS
    // =========================================================================
    private static function render_styles()
    {
        ?>
        <style>
            .sapwc-selective-import { max-width: 1200px; }
            .sapwc-selective-section { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
            .sapwc-section-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
            .sapwc-section-header h2 { margin: 0; color: #1d2327; }
            .sapwc-section-actions { display: flex; gap: 10px; flex-wrap: wrap; }
            
            .sapwc-loading-indicator { padding: 20px; text-align: center; color: #666; }
            .sapwc-loading-indicator .spinner { float: none; margin: 0 10px 0 0; }
            
            .sapwc-results-info { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
            .sapwc-results-info .dashicons { color: #2271b1; }
            
            .sapwc-selected-count { font-weight: bold; margin-left: 4px; }
            
            /* Tabla de pendientes */
            #sapwc-pending-products-table th.check-column,
            #sapwc-pending-categories-table th.check-column,
            #sapwc-pending-customers-table th.check-column { width: 40px; padding: 8px; }
            
            .sapwc-row-actions { display: flex; gap: 8px; }
            .sapwc-row-actions .button { padding: 4px 10px; font-size: 12px; }
            .sapwc-row-actions .button .dashicons { font-size: 14px; width: 14px; height: 14px; vertical-align: middle; }
            
            .sapwc-status-imported { color: #46b450; font-weight: 500; }
            .sapwc-status-importing { color: #f0b849; }
            .sapwc-status-error { color: #dc3232; }

            /* Modal */
            .sapwc-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 100100; }
            .sapwc-modal-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); }
            .sapwc-modal-content { position: relative; background: #fff; max-width: 900px; margin: 50px auto; border-radius: 8px; box-shadow: 0 5px 40px rgba(0,0,0,0.3); max-height: calc(100vh - 100px); display: flex; flex-direction: column; }
            .sapwc-modal-header { padding: 20px 24px; border-bottom: 1px solid #dcdcde; display: flex; justify-content: space-between; align-items: center; }
            .sapwc-modal-header h2 { margin: 0; font-size: 18px; }
            .sapwc-modal-close { background: none; border: none; font-size: 28px; cursor: pointer; color: #999; line-height: 1; padding: 0; }
            .sapwc-modal-close:hover { color: #333; }
            .sapwc-modal-body { padding: 24px; overflow-y: auto; flex: 1; }
            .sapwc-modal-footer { padding: 16px 24px; border-top: 1px solid #dcdcde; display: flex; justify-content: flex-end; gap: 12px; }
            
            .sapwc-preview-loading { text-align: center; padding: 40px; }
            .sapwc-preview-loading .spinner { float: none; margin-right: 10px; }
            
            .sapwc-preview-columns { display: grid; grid-template-columns: 1fr auto 1fr; gap: 20px; align-items: start; }
            .sapwc-preview-arrow { display: flex; align-items: center; justify-content: center; padding-top: 60px; }
            .sapwc-preview-arrow .dashicons { font-size: 32px; color: #2271b1; }
            
            .sapwc-preview-sap h3,
            .sapwc-preview-woo h3 { margin-top: 0; font-size: 14px; display: flex; align-items: center; gap: 6px; color: #1d2327; }
            .sapwc-preview-sap h3 .dashicons { color: #f0b849; }
            .sapwc-preview-woo h3 .dashicons { color: #0073aa; }
            
            .sapwc-preview-sap table,
            .sapwc-preview-woo table { font-size: 13px; }
            .sapwc-preview-sap td,
            .sapwc-preview-woo td { word-break: break-word; }
            
            .sapwc-preview-status { margin-top: 20px; padding: 12px 15px; border-radius: 4px; }
            .sapwc-preview-status.status-exists { background: #fcf9e8; border-left: 4px solid #dba617; }
            .sapwc-preview-status.status-new { background: #edf7ed; border-left: 4px solid #46b450; }

            /* Progress */
            .sapwc-import-progress { margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 6px; }
            .sapwc-import-progress h4 { margin-top: 0; }
            .sapwc-progress-bar { height: 28px; background: #e5e5e5; border-radius: 14px; overflow: hidden; margin-bottom: 15px; }
            .sapwc-progress-fill { height: 100%; background: linear-gradient(90deg, #2271b1, #135e96); border-radius: 14px; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 13px; min-width: 40px; }
            .sapwc-progress-fill.done { background: linear-gradient(90deg, #46b450, #2e7d32); }
            .sapwc-progress-fill.error { background: linear-gradient(90deg, #dc3232, #a00); }
            .sapwc-progress-log { max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 10px; line-height: 1.8; }
            .sapwc-progress-log .log-success { color: #2e7d32; }
            .sapwc-progress-log .log-error { color: #c62828; }
            .sapwc-progress-log .log-info { color: #1565c0; }

            /* Responsive */
            @media (max-width: 782px) {
                .sapwc-section-header { flex-direction: column; align-items: flex-start; }
                .sapwc-preview-columns { grid-template-columns: 1fr; }
                .sapwc-preview-arrow { padding: 10px 0; transform: rotate(90deg); }
                .sapwc-modal-content { margin: 20px; max-height: calc(100vh - 40px); }
            }
        </style>
        <?php
    }

    // =========================================================================
    // SCRIPTS
    // =========================================================================
    private static function render_scripts()
    {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Estado global
            let currentPreviewType = '';
            let currentPreviewId = '';
            let selectedItems = { products: [], categories: [], customers: [] };

            // ============================
            // MODAL FUNCTIONS
            // ============================
            function openModal() {
                $('#sapwc-preview-modal').fadeIn(200);
                $('body').css('overflow', 'hidden');
            }
            
            function closeModal() {
                $('#sapwc-preview-modal').fadeOut(200);
                $('body').css('overflow', '');
                currentPreviewType = '';
                currentPreviewId = '';
            }

            $('.sapwc-modal-close, #sapwc-modal-cancel, .sapwc-modal-overlay').on('click', closeModal);
            
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') closeModal();
            });

            // ============================
            // PRODUCTOS
            // ============================
            $('#sapwc-load-pending-products').on('click', function() {
                const $btn = $(this);
                const $loading = $('#products-loading');
                const $table = $('#sapwc-pending-products-table');
                const $info = $('#products-results-info');

                $btn.prop('disabled', true);
                $loading.show();
                $table.hide();
                $info.hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_get_pending_products',
                    nonce: sapwc_ajax.nonce
                }).done(function(res) {
                    if (res.success) {
                        const items = res.data.items || [];
                        const $tbody = $table.find('tbody');
                        $tbody.empty();
                        selectedItems.products = [];

                        if (items.length === 0) {
                            $info.show().find('span:last').text('No hay productos pendientes de importar. Todos los productos de SAP ya existen en WooCommerce.');
                        } else {
                            $info.show().find('span:last').text(`Se encontraron ${items.length} productos en SAP que no existen en WooCommerce.`);
                            
                            items.forEach(function(item) {
                                const code = $('<div>').text(item.ItemCode || '').html();
                                const name = $('<div>').text(item.ItemName || '').html();
                                const group = item.ItemsGroupCode || '-';
                                
                                $tbody.append(`
                                    <tr data-code="${code}">
                                        <td class="check-column"><input type="checkbox" class="sapwc-select-product" value="${code}"></td>
                                        <td><code>${code}</code></td>
                                        <td>${name}</td>
                                        <td>${group}</td>
                                        <td class="sapwc-row-actions">
                                            <button class="button sapwc-preview-product" data-code="${code}" title="Vista previa">
                                                <span class="dashicons dashicons-visibility" style="font-family:dashicons;"></span> Preview
                                            </button>
                                            <button class="button button-primary sapwc-import-single-product" data-code="${code}" title="Importar">
                                                <span class="dashicons dashicons-download" style="font-family:dashicons;"></span>
                                            </button>
                                        </td>
                                    </tr>
                                `);
                            });
                            
                            $table.show();
                        }
                    } else {
                        alert('Error: ' + (res.data?.message || 'Error desconocido'));
                    }
                }).fail(function() {
                    alert('Error de conexión');
                }).always(function() {
                    $btn.prop('disabled', false);
                    $loading.hide();
                });
            });

            // Preview producto
            $(document).on('click', '.sapwc-preview-product', function() {
                const code = $(this).data('code');
                currentPreviewType = 'product';
                currentPreviewId = code;
                
                openModal();
                $('#sapwc-modal-title').text('Vista Previa: Producto ' + code);
                $('.sapwc-preview-loading').show();
                $('.sapwc-preview-content').hide();
                $('#sapwc-preview-status').hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_preview_product',
                    nonce: sapwc_ajax.nonce,
                    item_code: code
                }).done(function(res) {
                    $('.sapwc-preview-loading').hide();
                    if (res.success) {
                        renderPreview(res.data);
                        $('.sapwc-preview-content').show();
                    } else {
                        alert('Error: ' + (res.data?.message || res.data?.error || 'Error'));
                        closeModal();
                    }
                }).fail(function() {
                    alert('Error de conexión');
                    closeModal();
                });
            });

            // Importar producto individual
            $(document).on('click', '.sapwc-import-single-product', function() {
                const $btn = $(this);
                const code = $btn.data('code');
                const $row = $btn.closest('tr');
                
                $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_import_single_product',
                    nonce: sapwc_ajax.nonce,
                    item_code: code
                }).done(function(res) {
                    if (res.success) {
                        $row.find('.sapwc-row-actions').html('<span class="sapwc-status-imported"><span class="dashicons dashicons-yes-alt" style="font-family:dashicons;"></span> Importado</span>');
                        $row.find('.sapwc-select-product').prop('checked', false).prop('disabled', true);
                    } else {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;"></span>');
                        alert('Error: ' + (res.data?.message || 'Error'));
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;"></span>');
                    alert('Error de conexión');
                });
            });

            // ============================
            // CATEGORÍAS
            // ============================
            $('#sapwc-load-pending-categories').on('click', function() {
                const $btn = $(this);
                const $loading = $('#categories-loading');
                const $table = $('#sapwc-pending-categories-table');
                const $info = $('#categories-results-info');

                $btn.prop('disabled', true);
                $loading.show();
                $table.hide();
                $info.hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_get_pending_categories',
                    nonce: sapwc_ajax.nonce
                }).done(function(res) {
                    if (res.success) {
                        const items = res.data.items || [];
                        const $tbody = $table.find('tbody');
                        $tbody.empty();
                        selectedItems.categories = [];

                        if (items.length === 0) {
                            $info.show().find('span:last').text('No hay categorías pendientes. Todos los grupos de SAP ya existen en WooCommerce.');
                        } else {
                            $info.show().find('span:last').text(`Se encontraron ${items.length} categorías en SAP que no existen en WooCommerce.`);
                            
                            items.forEach(function(item) {
                                const num = item.Number;
                                const name = $('<div>').text(item.GroupName || '').html();
                                
                                $tbody.append(`
                                    <tr data-number="${num}">
                                        <td class="check-column"><input type="checkbox" class="sapwc-select-category" value="${num}"></td>
                                        <td><code>${num}</code></td>
                                        <td><strong>${name}</strong></td>
                                        <td class="sapwc-row-actions">
                                            <button class="button sapwc-preview-category" data-number="${num}" title="Vista previa">
                                                <span class="dashicons dashicons-visibility" style="font-family:dashicons;"></span> Preview
                                            </button>
                                            <button class="button button-primary sapwc-import-single-category" data-number="${num}" title="Importar">
                                                <span class="dashicons dashicons-download" style="font-family:dashicons;"></span>
                                            </button>
                                        </td>
                                    </tr>
                                `);
                            });
                            
                            $table.show();
                        }
                    } else {
                        alert('Error: ' + (res.data?.message || 'Error desconocido'));
                    }
                }).fail(function() {
                    alert('Error de conexión');
                }).always(function() {
                    $btn.prop('disabled', false);
                    $loading.hide();
                });
            });

            // Preview categoría
            $(document).on('click', '.sapwc-preview-category', function() {
                const num = $(this).data('number');
                currentPreviewType = 'category';
                currentPreviewId = num;
                
                openModal();
                $('#sapwc-modal-title').text('Vista Previa: Categoría #' + num);
                $('.sapwc-preview-loading').show();
                $('.sapwc-preview-content').hide();
                $('#sapwc-preview-status').hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_preview_category',
                    nonce: sapwc_ajax.nonce,
                    group_number: num
                }).done(function(res) {
                    $('.sapwc-preview-loading').hide();
                    if (res.success) {
                        renderPreview(res.data);
                        $('.sapwc-preview-content').show();
                    } else {
                        alert('Error: ' + (res.data?.message || res.data?.error || 'Error'));
                        closeModal();
                    }
                }).fail(function() {
                    alert('Error de conexión');
                    closeModal();
                });
            });

            // Importar categoría individual
            $(document).on('click', '.sapwc-import-single-category', function() {
                const $btn = $(this);
                const num = $btn.data('number');
                const $row = $btn.closest('tr');
                
                $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_import_single_category',
                    nonce: sapwc_ajax.nonce,
                    group_number: num
                }).done(function(res) {
                    if (res.success) {
                        $row.find('.sapwc-row-actions').html('<span class="sapwc-status-imported"><span class="dashicons dashicons-yes-alt" style="font-family:dashicons;"></span> Importada</span>');
                        $row.find('.sapwc-select-category').prop('checked', false).prop('disabled', true);
                    } else {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;"></span>');
                        alert('Error: ' + (res.data?.message || 'Error'));
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;"></span>');
                    alert('Error de conexión');
                });
            });

            // ============================
            // CLIENTES
            // ============================
            $('#sapwc-load-pending-customers').on('click', function() {
                const $btn = $(this);
                const $loading = $('#customers-loading');
                const $table = $('#sapwc-pending-customers-table');
                const $info = $('#customers-results-info');

                $btn.prop('disabled', true);
                $loading.show();
                $table.hide();
                $info.hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_get_pending_customers',
                    nonce: sapwc_ajax.nonce
                }).done(function(res) {
                    if (res.success) {
                        const items = res.data.items || [];
                        const $tbody = $table.find('tbody');
                        $tbody.empty();
                        selectedItems.customers = [];

                        if (items.length === 0) {
                            $info.show().find('span:last').text('No hay clientes pendientes. Todos los clientes web de SAP ya existen en WooCommerce.');
                        } else {
                            $info.show().find('span:last').text(`Se encontraron ${items.length} clientes en SAP que no existen en WooCommerce.`);
                            
                            items.forEach(function(item) {
                                const code = $('<div>').text(item.CardCode || '').html();
                                const name = $('<div>').text(item.CardName || '').html();
                                const email = $('<div>').text(item.EmailAddress || '-').html();
                                const nif = $('<div>').text(item.FederalTaxID || '-').html();
                                
                                $tbody.append(`
                                    <tr data-code="${code}">
                                        <td class="check-column"><input type="checkbox" class="sapwc-select-customer" value="${code}"></td>
                                        <td><code>${code}</code></td>
                                        <td>${name}</td>
                                        <td>${email}</td>
                                        <td>${nif}</td>
                                        <td class="sapwc-row-actions">
                                            <button class="button sapwc-preview-customer" data-code="${code}" title="Vista previa">
                                                <span class="dashicons dashicons-visibility" style="font-family:dashicons;"></span> Preview
                                            </button>
                                            <button class="button button-primary sapwc-import-single-customer" data-code="${code}" title="Importar">
                                                <span class="dashicons dashicons-download" style="font-family:dashicons;"></span>
                                            </button>
                                        </td>
                                    </tr>
                                `);
                            });
                            
                            $table.show();
                        }
                    } else {
                        alert('Error: ' + (res.data?.message || 'Error desconocido'));
                    }
                }).fail(function() {
                    alert('Error de conexión');
                }).always(function() {
                    $btn.prop('disabled', false);
                    $loading.hide();
                });
            });

            // Preview cliente
            $(document).on('click', '.sapwc-preview-customer', function() {
                const code = $(this).data('code');
                currentPreviewType = 'customer';
                currentPreviewId = code;
                
                openModal();
                $('#sapwc-modal-title').text('Vista Previa: Cliente ' + code);
                $('.sapwc-preview-loading').show();
                $('.sapwc-preview-content').hide();
                $('#sapwc-preview-status').hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_preview_customer',
                    nonce: sapwc_ajax.nonce,
                    cardcode: code
                }).done(function(res) {
                    $('.sapwc-preview-loading').hide();
                    if (res.success) {
                        renderPreview(res.data);
                        $('.sapwc-preview-content').show();
                    } else {
                        alert('Error: ' + (res.data?.message || res.data?.error || 'Error'));
                        closeModal();
                    }
                }).fail(function() {
                    alert('Error de conexión');
                    closeModal();
                });
            });

            // Importar cliente individual
            $(document).on('click', '.sapwc-import-single-customer', function() {
                const $btn = $(this);
                const code = $btn.data('code');
                const $row = $btn.closest('tr');
                
                $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_import_single_customer',
                    nonce: sapwc_ajax.nonce,
                    cardcode: code
                }).done(function(res) {
                    if (res.success) {
                        $row.find('.sapwc-row-actions').html('<span class="sapwc-status-imported"><span class="dashicons dashicons-yes-alt" style="font-family:dashicons;"></span> Importado</span>');
                        $row.find('.sapwc-select-customer').prop('checked', false).prop('disabled', true);
                    } else {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;"></span>');
                        alert('Error: ' + (res.data || 'Error'));
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;"></span>');
                    alert('Error de conexión');
                });
            });

            // ============================
            // RENDER PREVIEW
            // ============================
            function renderPreview(data) {
                // SAP data
                const $sapTbody = $('#sapwc-preview-sap-table tbody');
                $sapTbody.empty();
                (data.sap_data || []).forEach(function(row) {
                    const val = row.value !== null && row.value !== '' ? $('<div>').text(row.value).html() : '<em style="color:#999;">-</em>';
                    $sapTbody.append(`<tr><td><strong>${row.label}</strong></td><td>${val}</td></tr>`);
                });

                // WooCommerce mapping
                const $wooTbody = $('#sapwc-preview-woo-table tbody');
                $wooTbody.empty();
                (data.woo_mapping || []).forEach(function(row) {
                    const source = row.source ? $('<div>').text(row.source).html() : '<em style="color:#999;">-</em>';
                    $wooTbody.append(`<tr><td><strong>${row.label}</strong><br><code style="font-size:11px;">${row.field}</code></td><td>${source}</td></tr>`);
                });

                // Status
                const $status = $('#sapwc-preview-status');
                if (data.exists_in_woo) {
                    $status.removeClass('status-new').addClass('status-exists').html('<span class="dashicons dashicons-warning" style="font-family:dashicons;color:#dba617;"></span> Este elemento ya existe en WooCommerce. Se actualizará.').show();
                    $('#sapwc-modal-import').text(' Actualizar Ahora');
                } else {
                    $status.removeClass('status-exists').addClass('status-new').html('<span class="dashicons dashicons-yes" style="font-family:dashicons;color:#46b450;"></span> Este elemento no existe en WooCommerce. Se creará nuevo.').show();
                    $('#sapwc-modal-import').html('<span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span> Importar Ahora');
                }
            }

            // ============================
            // IMPORT FROM MODAL
            // ============================
            $('#sapwc-modal-import').on('click', function() {
                const $btn = $(this);
                $btn.prop('disabled', true).text('Importando...');

                let action = '';
                let data = { nonce: sapwc_ajax.nonce };

                switch (currentPreviewType) {
                    case 'product':
                        action = 'sapwc_import_single_product';
                        data.item_code = currentPreviewId;
                        break;
                    case 'category':
                        action = 'sapwc_import_single_category';
                        data.group_number = currentPreviewId;
                        break;
                    case 'customer':
                        action = 'sapwc_import_single_customer';
                        data.cardcode = currentPreviewId;
                        break;
                }

                data.action = action;

                $.post(sapwc_ajax.ajax_url, data).done(function(res) {
                    if (res.success) {
                        closeModal();
                        // Marcar fila como importada
                        const selector = currentPreviewType === 'category' 
                            ? `tr[data-number="${currentPreviewId}"]`
                            : `tr[data-code="${currentPreviewId}"]`;
                        $(selector).find('.sapwc-row-actions').html('<span class="sapwc-status-imported"><span class="dashicons dashicons-yes-alt" style="font-family:dashicons;"></span> Importado</span>');
                        $(selector).find('input[type="checkbox"]').prop('checked', false).prop('disabled', true);
                    } else {
                        alert('Error: ' + (res.data?.message || res.data || 'Error'));
                    }
                }).fail(function() {
                    alert('Error de conexión');
                }).always(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span> Importar Ahora');
                });
            });

            // ============================
            // CHECKBOX SELECTION
            // ============================
            function updateSelectionCount(type) {
                const checked = $(`.sapwc-select-${type}:checked:not(:disabled)`).length;
                $(`#sapwc-import-selected-${type}s`).prop('disabled', checked === 0).find('.sapwc-selected-count').text(`(${checked})`);
            }

            $(document).on('change', '.sapwc-select-product', function() { updateSelectionCount('product'); });
            $(document).on('change', '.sapwc-select-category', function() { updateSelectionCount('category'); });
            $(document).on('change', '.sapwc-select-customer', function() { updateSelectionCount('customer'); });

            // Select all
            $('#sapwc-select-all-products').on('change', function() {
                $('.sapwc-select-product:not(:disabled)').prop('checked', $(this).is(':checked'));
                updateSelectionCount('product');
            });
            $('#sapwc-select-all-categories').on('change', function() {
                $('.sapwc-select-category:not(:disabled)').prop('checked', $(this).is(':checked'));
                updateSelectionCount('category');
            });
            $('#sapwc-select-all-customers').on('change', function() {
                $('.sapwc-select-customer:not(:disabled)').prop('checked', $(this).is(':checked'));
                updateSelectionCount('customer');
            });

            // ============================
            // BULK IMPORT (SELECTED)
            // ============================
            function bulkImport(type, action, idKey, idAttr) {
                const $items = $(`.sapwc-select-${type}:checked:not(:disabled)`);
                if ($items.length === 0) return;

                const items = [];
                $items.each(function() { items.push($(this).val()); });

                const $progress = $(`#${type}s-import-progress`);
                const $fill = $(`#${type}s-progress-fill`);
                const $log = $(`#${type}s-import-log`);
                
                $progress.show();
                $fill.css('width', '0%').text('0%').removeClass('done error');
                $log.empty();

                let completed = 0;
                let errors = 0;

                function importNext(index) {
                    if (index >= items.length) {
                        const pct = 100;
                        $fill.css('width', pct + '%').text(pct + '%').addClass(errors > 0 ? 'error' : 'done');
                        const ts = new Date().toLocaleTimeString();
                        $log.append(`<div class="log-${errors > 0 ? 'error' : 'success'}">[${ts}] Completado: ${completed} importados, ${errors} errores</div>`);
                        return;
                    }

                    const id = items[index];
                    const data = { action: action, nonce: sapwc_ajax.nonce };
                    data[idKey] = id;

                    const ts = new Date().toLocaleTimeString();
                    $log.append(`<div class="log-info">[${ts}] Importando ${id}...</div>`);
                    $log.scrollTop($log[0].scrollHeight);

                    $.post(sapwc_ajax.ajax_url, data).done(function(res) {
                        if (res.success) {
                            completed++;
                            const selector = idAttr === 'data-number' ? `tr[data-number="${id}"]` : `tr[data-code="${id}"]`;
                            $(selector).find('.sapwc-row-actions').html('<span class="sapwc-status-imported"><span class="dashicons dashicons-yes-alt" style="font-family:dashicons;"></span> Importado</span>');
                            $(selector).find('input[type="checkbox"]').prop('checked', false).prop('disabled', true);
                            $log.append(`<div class="log-success">[${new Date().toLocaleTimeString()}] ${id} importado correctamente</div>`);
                        } else {
                            errors++;
                            $log.append(`<div class="log-error">[${new Date().toLocaleTimeString()}] Error en ${id}: ${res.data?.message || res.data || 'Error'}</div>`);
                        }
                    }).fail(function() {
                        errors++;
                        $log.append(`<div class="log-error">[${new Date().toLocaleTimeString()}] Error de conexión para ${id}</div>`);
                    }).always(function() {
                        const pct = Math.round(((index + 1) / items.length) * 100);
                        $fill.css('width', pct + '%').text(pct + '%');
                        $log.scrollTop($log[0].scrollHeight);
                        setTimeout(function() { importNext(index + 1); }, 300);
                    });
                }

                importNext(0);
            }

            $('#sapwc-import-selected-products').on('click', function() {
                bulkImport('product', 'sapwc_import_single_product', 'item_code', 'data-code');
            });
            $('#sapwc-import-selected-categories').on('click', function() {
                bulkImport('category', 'sapwc_import_single_category', 'group_number', 'data-number');
            });
            $('#sapwc-import-selected-customers').on('click', function() {
                bulkImport('customer', 'sapwc_import_single_customer', 'cardcode', 'data-code');
            });

        });
        </script>
        <?php
    }
}

// =============================================================================
// AJAX ENDPOINTS
// =============================================================================

/**
 * Obtener productos pendientes (no importados)
 */
add_action('wp_ajax_sapwc_get_pending_products', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Product_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Product_Sync::get_pending_products(0, 100);

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success($result);
});

/**
 * Preview de producto individual
 */
add_action('wp_ajax_sapwc_preview_product', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $item_code = sanitize_text_field($_POST['item_code'] ?? '');
    if (empty($item_code)) {
        wp_send_json_error(['message' => __('ItemCode no proporcionado.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Product_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Product_Sync::get_product_preview($item_code);

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success($result);
});

/**
 * Importar producto individual
 */
add_action('wp_ajax_sapwc_import_single_product', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $item_code = sanitize_text_field($_POST['item_code'] ?? '');
    if (empty($item_code)) {
        wp_send_json_error(['message' => __('ItemCode no proporcionado.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Product_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    // Obtener el producto desde SAP
    $preview = SAPWC_Product_Sync::get_product_preview($item_code);
    if (isset($preview['error'])) {
        wp_send_json_error(['message' => $preview['error']]);
    }

    // Importar usando los datos raw
    $result = SAPWC_Product_Sync::import_product($preview['raw'], [
        'update_existing' => true,
        'import_prices' => true,
        'import_stock' => true,
        'assign_category' => true,
        'default_status' => 'draft',
    ]);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

/**
 * Obtener categorías pendientes
 */
add_action('wp_ajax_sapwc_get_pending_categories', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Category_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Category_Sync::get_pending_categories();

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success($result);
});

/**
 * Preview de categoría individual
 */
add_action('wp_ajax_sapwc_preview_category', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $group_number = intval($_POST['group_number'] ?? 0);
    if ($group_number <= 0) {
        wp_send_json_error(['message' => __('Número de grupo no válido.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Category_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Category_Sync::get_category_preview($group_number);

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success($result);
});

/**
 * Importar categoría individual
 */
add_action('wp_ajax_sapwc_import_single_category', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $group_number = intval($_POST['group_number'] ?? 0);
    if ($group_number <= 0) {
        wp_send_json_error(['message' => __('Número de grupo no válido.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Category_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    // Obtener datos de SAP
    $preview = SAPWC_Category_Sync::get_category_preview($group_number);
    if (isset($preview['error'])) {
        wp_send_json_error(['message' => $preview['error']]);
    }

    // Importar
    $result = SAPWC_Category_Sync::import_category($preview['raw']);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

/**
 * Obtener clientes pendientes
 */
add_action('wp_ajax_sapwc_get_pending_customers', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    if (get_option('sapwc_mode', 'ecommerce') !== 'b2b') {
        wp_send_json_error(['message' => __('Solo disponible en modo B2B.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Customer_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $items = SAPWC_Customer_Sync::get_pending_web_customers(100);

    wp_send_json_success(['items' => $items, 'total' => count($items)]);
});

/**
 * Preview de cliente individual
 */
add_action('wp_ajax_sapwc_preview_customer', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $cardcode = sanitize_text_field($_POST['cardcode'] ?? '');
    if (empty($cardcode)) {
        wp_send_json_error(['message' => __('CardCode no proporcionado.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Customer_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Customer_Sync::get_customer_preview($cardcode);

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success($result);
});
