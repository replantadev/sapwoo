<?php
/**
 * Página de Importación Unificada
 * 
 * Tabs: Productos | Categorías | Clientes
 * Cada tab tiene un importador asíncrono por lotes con barra de progreso.
 *
 * @package SAPWC
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAPWC_Import_Page
{
    public static function render()
    {
        $active_tab = sanitize_text_field($_GET['tab'] ?? 'products');
        $valid_tabs = ['products', 'categories', 'customers'];
        if (!in_array($active_tab, $valid_tabs)) {
            $active_tab = 'products';
        }

        ?>
        <div class="wrap sapwc-settings">
            <h1><span class="dashicons dashicons-download"></span> <?php esc_html_e('Importación desde SAP', 'sapwoo'); ?></h1>
            <p class="description"><?php esc_html_e('Importa datos del catálogo de SAP Business One a WooCommerce. Las importaciones se ejecutan por lotes de forma asíncrona.', 'sapwoo'); ?></p>

            <div class="notice notice-info inline" style="margin: 15px 0; padding: 12px 16px;">
                <h3 style="margin-top:0;"><span class="dashicons dashicons-editor-help" style="font-family:dashicons;"></span> <?php esc_html_e('Guía rápida para el operario', 'sapwoo'); ?></h3>
                <ol style="margin: 8px 0 0 1.5em; line-height: 1.8;">
                    <li><strong><?php esc_html_e('Categorías primero:', 'sapwoo'); ?></strong> <?php esc_html_e('Importa las categorías antes que los productos para que se vincule automáticamente cada producto a su grupo de SAP.', 'sapwoo'); ?></li>
                    <li><strong><?php esc_html_e('Productos después:', 'sapwoo'); ?></strong> <?php esc_html_e('La importación se ejecuta en lotes de 20. Puedes pausar y reanudar en cualquier momento. Los productos existentes se actualizan por SKU.', 'sapwoo'); ?></li>
                    <li><strong><?php esc_html_e('Clientes (solo B2B):', 'sapwoo'); ?></strong> <?php esc_html_e('Importa los socio de negocio (BusinessPartners) como usuarios de WooCommerce. Útil para catálogos B2B con precios personalizados.', 'sapwoo'); ?></li>
                </ol>
                <p style="margin-bottom:0; color:#666;"><span class="dashicons dashicons-info" style="font-family:dashicons;font-size:14px;vertical-align:text-bottom;"></span> <?php esc_html_e('La conexión con SAP debe estar activa antes de importar. Verifica las credenciales en Ajustes si hay errores de conexión.', 'sapwoo'); ?></p>
            </div>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sapwc-import&tab=products')); ?>"
                   class="nav-tab <?php echo $active_tab === 'products' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-products" style="font-family:dashicons;"></span> <?php esc_html_e('Productos', 'sapwoo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sapwc-import&tab=categories')); ?>"
                   class="nav-tab <?php echo $active_tab === 'categories' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-category" style="font-family:dashicons;"></span> <?php esc_html_e('Categorías', 'sapwoo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sapwc-import&tab=customers')); ?>"
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
        </div>

        <!-- Modal de Preview para Importación Selectiva -->
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
                                <h3><span class="dashicons dashicons-database" style="font-family:dashicons;"></span> <?php esc_html_e('Datos en SAP', 'sapwoo'); ?></h3>
                                <table class="wp-list-table widefat striped" id="sapwc-preview-sap-table">
                                    <thead><tr><th><?php esc_html_e('Campo', 'sapwoo'); ?></th><th><?php esc_html_e('Valor', 'sapwoo'); ?></th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="sapwc-preview-arrow">
                                <span class="dashicons dashicons-arrow-right-alt" style="font-family:dashicons;font-size:32px;"></span>
                            </div>
                            <div class="sapwc-preview-woo">
                                <h3><span class="dashicons dashicons-wordpress" style="font-family:dashicons;"></span> <?php esc_html_e('Destino en WooCommerce', 'sapwoo'); ?></h3>
                                <table class="wp-list-table widefat striped" id="sapwc-preview-woo-table">
                                    <thead><tr><th><?php esc_html_e('Campo WooCommerce', 'sapwoo'); ?></th><th><?php esc_html_e('Origen SAP', 'sapwoo'); ?></th></tr></thead>
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

        <?php self::render_shared_styles(); ?>
        <?php self::render_shared_scripts(); ?>
        <?php self::render_selective_scripts(); ?>
        <?php
    }

    // =========================================================================
    // TAB: PRODUCTOS
    // =========================================================================
    private static function render_products_tab()
    {
        $stats = class_exists('SAPWC_Product_Sync') ? SAPWC_Product_Sync::get_stats() : ['total_imported' => 0, 'total_woo' => 0, 'last_sync' => __('Nunca', 'sapwoo')];
        ?>
        <div class="sapwc-import-section">
            <h2><?php esc_html_e('Importar Productos desde SAP', 'sapwoo'); ?></h2>
            <p><?php esc_html_e('Importa artículos (Items) de SAP como productos de WooCommerce. Los productos existentes se actualizan por SKU.', 'sapwoo'); ?></p>

            <!-- Stats -->
            <div class="sapwc-stats-grid">
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number" id="stat-products-imported"><?php echo esc_html($stats['total_imported']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Importados de SAP', 'sapwoo'); ?></span>
                </div>
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number"><?php echo esc_html($stats['total_woo']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Total en WooCommerce', 'sapwoo'); ?></span>
                </div>
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number sapwc-stat-date" id="stat-products-lastsync"><?php echo esc_html($stats['last_sync']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Última importación', 'sapwoo'); ?></span>
                </div>
            </div>

            <!-- Options -->
            <div class="sapwc-import-options">
                <h3><?php esc_html_e('Opciones de importación', 'sapwoo'); ?></h3>
                <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Marca las opciones que deseas aplicar durante la importación. Si desmarcas una opción, ese dato no se sobrescribirá en los productos existentes.', 'sapwoo'); ?></p>
                <label>
                    <input type="checkbox" id="sapwc-opt-update-existing" checked>
                    <?php esc_html_e('Actualizar productos existentes (precio, stock, nombre)', 'sapwoo'); ?>
                </label><br>
                <label>
                    <input type="checkbox" id="sapwc-opt-import-prices" checked>
                    <?php esc_html_e('Importar precios (según tarifa configurada)', 'sapwoo'); ?>
                </label><br>
                <label>
                    <input type="checkbox" id="sapwc-opt-import-stock" checked>
                    <?php esc_html_e('Importar stock (según almacenes configurados)', 'sapwoo'); ?>
                </label><br>
                <label>
                    <input type="checkbox" id="sapwc-opt-assign-category" checked>
                    <?php esc_html_e('Asignar categoría por ItemsGroupCode (requiere importar categorías primero)', 'sapwoo'); ?>
                </label><br>
                <label>
                    <?php esc_html_e('Estado inicial de nuevos productos:', 'sapwoo'); ?>
                    <select id="sapwc-opt-default-status">
                        <option value="draft"><?php esc_html_e('Borrador', 'sapwoo'); ?></option>
                        <option value="publish"><?php esc_html_e('Publicado', 'sapwoo'); ?></option>
                    </select>
                </label>
            </div>

            <!-- Progress -->
            <div class="sapwc-import-controls">
                <button id="sapwc-import-products-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                    <?php esc_html_e('Iniciar Importación de Productos', 'sapwoo'); ?>
                </button>
                <button id="sapwc-stop-products-btn" class="button button-secondary" style="display:none;">
                    <span class="dashicons dashicons-controls-pause" style="font-family:dashicons;vertical-align:middle;"></span>
                    <?php esc_html_e('Pausar', 'sapwoo'); ?>
                </button>
            </div>

            <div class="sapwc-progress-wrapper" id="products-progress-wrapper" style="display:none;">
                <div class="sapwc-progress-bar">
                    <div class="sapwc-progress-fill" id="products-progress-fill" style="width: 0%;">0%</div>
                </div>
                <div class="sapwc-progress-log" id="products-progress-log"></div>
            </div>

            <!-- Importación Selectiva de Productos -->
            <div class="sapwc-selective-section" style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                <div class="sapwc-section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <h3 style="margin:0;"><span class="dashicons dashicons-filter" style="font-family:dashicons;"></span> <?php esc_html_e('Importación Selectiva', 'sapwoo'); ?></h3>
                    <div class="sapwc-section-actions">
                        <button id="sapwc-load-pending-products" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="font-family:dashicons;vertical-align:middle;"></span>
                            <?php esc_html_e('Cargar Pendientes', 'sapwoo'); ?>
                        </button>
                        <button id="sapwc-import-selected-products" class="button" disabled>
                            <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                            <?php esc_html_e('Importar Seleccionados', 'sapwoo'); ?>
                            <span class="sapwc-selected-count">(0)</span>
                        </button>
                    </div>
                </div>
                <p class="description"><?php esc_html_e('Muestra productos que están en SAP pero todavía no se han importado a WooCommerce. Puedes previsualizar y seleccionar cuáles importar.', 'sapwoo'); ?></p>

                <div class="sapwc-loading-indicator" id="products-pending-loading" style="display:none;">
                    <span class="spinner is-active"></span> <?php esc_html_e('Consultando SAP...', 'sapwoo'); ?>
                </div>
                <div class="sapwc-results-info" id="products-results-info" style="display:none;">
                    <span class="dashicons dashicons-info" style="font-family:dashicons;"></span>
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
                <div class="sapwc-import-progress" id="products-selective-progress" style="display:none;">
                    <div class="sapwc-progress-bar">
                        <div class="sapwc-progress-fill" id="products-selective-fill">0%</div>
                    </div>
                    <div class="sapwc-progress-log" id="products-selective-log"></div>
                </div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // TAB: CATEGORÍAS
    // =========================================================================
    private static function render_categories_tab()
    {
        $stats = class_exists('SAPWC_Category_Sync') ? SAPWC_Category_Sync::get_stats() : ['total_imported' => 0, 'total_woo' => 0, 'last_sync' => __('Nunca', 'sapwoo')];
        ?>
        <div class="sapwc-import-section">
            <h2><?php esc_html_e('Importar Categorías desde SAP', 'sapwoo'); ?></h2>
            <p><?php esc_html_e('Importa Grupos de Artículos (ItemGroups) de SAP como categorías de producto de WooCommerce.', 'sapwoo'); ?></p>
            <div class="notice notice-info inline">
                <p><span class="dashicons dashicons-info" style="font-family:dashicons;"></span> 
                <?php esc_html_e('Recomendación: importa las categorías ANTES que los productos para que se asignen automáticamente.', 'sapwoo'); ?></p>
            </div>

            <!-- Stats -->
            <div class="sapwc-stats-grid">
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number" id="stat-categories-imported"><?php echo esc_html($stats['total_imported']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Importadas de SAP', 'sapwoo'); ?></span>
                </div>
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number"><?php echo esc_html($stats['total_woo']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Total en WooCommerce', 'sapwoo'); ?></span>
                </div>
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number sapwc-stat-date" id="stat-categories-lastsync"><?php echo esc_html($stats['last_sync']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Última importación', 'sapwoo'); ?></span>
                </div>
            </div>

            <!-- Controls -->
            <div class="sapwc-import-controls">
                <button id="sapwc-import-categories-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-category" style="font-family:dashicons;vertical-align:middle;"></span>
                    <?php esc_html_e('Importar Categorías', 'sapwoo'); ?>
                </button>
            </div>

            <div class="sapwc-progress-wrapper" id="categories-progress-wrapper" style="display:none;">
                <div class="sapwc-progress-bar">
                    <div class="sapwc-progress-fill" id="categories-progress-fill" style="width: 0%;">0%</div>
                </div>
                <div class="sapwc-progress-log" id="categories-progress-log"></div>
            </div>

            <!-- Preview table -->
            <h3 style="margin-top: 2em;"><?php esc_html_e('Categorías en SAP', 'sapwoo'); ?></h3>
            <button id="sapwc-preview-categories-btn" class="button button-secondary" style="margin-bottom: 10px;">
                <span class="dashicons dashicons-visibility" style="font-family:dashicons;vertical-align:middle;"></span>
                <?php esc_html_e('Previsualizar Categorías de SAP', 'sapwoo'); ?>
            </button>
            <div id="sapwc-categories-preview" style="display:none;">
                <table class="wp-list-table widefat fixed striped" id="sapwc-categories-table">
                    <thead>
                        <tr>
                            <th style="width:80px;"><?php esc_html_e('Nº Grupo', 'sapwoo'); ?></th>
                            <th><?php esc_html_e('Nombre', 'sapwoo'); ?></th>
                            <th style="width:150px;"><?php esc_html_e('Estado en Woo', 'sapwoo'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- Importación Selectiva de Categorías -->
            <div class="sapwc-selective-section" style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                <div class="sapwc-section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <h3 style="margin:0;"><span class="dashicons dashicons-filter" style="font-family:dashicons;"></span> <?php esc_html_e('Importación Selectiva', 'sapwoo'); ?></h3>
                    <div class="sapwc-section-actions">
                        <button id="sapwc-load-pending-categories" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="font-family:dashicons;vertical-align:middle;"></span>
                            <?php esc_html_e('Cargar Pendientes', 'sapwoo'); ?>
                        </button>
                        <button id="sapwc-import-selected-categories" class="button" disabled>
                            <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                            <?php esc_html_e('Importar Seleccionadas', 'sapwoo'); ?>
                            <span class="sapwc-selected-count">(0)</span>
                        </button>
                    </div>
                </div>
                <p class="description"><?php esc_html_e('Categorías de SAP que todavía no existen en WooCommerce.', 'sapwoo'); ?></p>

                <div class="sapwc-loading-indicator" id="categories-pending-loading" style="display:none;">
                    <span class="spinner is-active"></span> <?php esc_html_e('Consultando SAP...', 'sapwoo'); ?>
                </div>
                <div class="sapwc-results-info" id="categories-results-info" style="display:none;">
                    <span class="dashicons dashicons-info" style="font-family:dashicons;"></span>
                    <span id="categories-count-text"></span>
                </div>
                <table id="sapwc-pending-categories-table" class="wp-list-table widefat fixed striped" style="display:none;">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="sapwc-select-all-categories"></th>
                            <th style="width:100px;"><?php esc_html_e('Nº Grupo', 'sapwoo'); ?></th>
                            <th><?php esc_html_e('Nombre', 'sapwoo'); ?></th>
                            <th style="width:180px;"><?php esc_html_e('Acciones', 'sapwoo'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="sapwc-import-progress" id="categories-selective-progress" style="display:none;">
                    <div class="sapwc-progress-bar">
                        <div class="sapwc-progress-fill" id="categories-selective-fill">0%</div>
                    </div>
                    <div class="sapwc-progress-log" id="categories-selective-log"></div>
                </div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // TAB: CLIENTES
    // =========================================================================
    private static function render_customers_tab()
    {
        $mode = get_option('sapwc_mode', 'ecommerce');
        $stats = class_exists('SAPWC_Customer_Sync') ? SAPWC_Customer_Sync::get_stats() : ['total_imported' => 0, 'last_sync' => __('Nunca', 'sapwoo'), 'emails_sent' => 0];
        $filter_type = get_option('sapwc_customer_filter_type', 'starts');
        $filter_value = sanitize_text_field(trim(get_option('sapwc_customer_filter_value', '')));

        ?>
        <div class="sapwc-import-section">
            <h2><?php esc_html_e('Importar Clientes desde SAP', 'sapwoo'); ?></h2>
            <p><?php esc_html_e('Importa socios de negocio (BusinessPartners) de SAP como usuarios de WooCommerce con rol de cliente.', 'sapwoo'); ?></p>
            <div class="notice notice-info inline" style="margin-bottom:15px;">
                <p><span class="dashicons dashicons-info" style="font-family:dashicons;"></span>
                <?php esc_html_e('Puedes importar clientes de forma masiva o buscar uno concreto en la tabla inferior. El filtro configurado en Opciones de Sincronización determina qué clientes se muestran.', 'sapwoo'); ?></p>
            </div>
            <p>
                <?php esc_html_e('Modo actual:', 'sapwoo'); ?> <strong><?php echo esc_html($mode); ?></strong>
                &nbsp;|&nbsp;
                <?php esc_html_e('Filtro:', 'sapwoo'); ?> <strong><?php echo esc_html($filter_type . ' → ' . ($filter_value ?: '(sin filtro)')); ?></strong>
            </p>

            <!-- Stats -->
            <div class="sapwc-stats-grid">
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number" id="stat-customers-imported"><?php echo esc_html($stats['total_imported']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Clientes importados', 'sapwoo'); ?></span>
                </div>
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number"><?php echo esc_html($stats['emails_sent']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Emails enviados', 'sapwoo'); ?></span>
                </div>
                <div class="sapwc-stat-card">
                    <span class="sapwc-stat-number sapwc-stat-date" id="stat-customers-lastsync"><?php echo esc_html($stats['last_sync']); ?></span>
                    <span class="sapwc-stat-label"><?php esc_html_e('Última importación', 'sapwoo'); ?></span>
                </div>
            </div>

            <!-- Controls -->
            <div class="sapwc-import-controls">
                <button id="sapwc-import-customers-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-groups" style="font-family:dashicons;vertical-align:middle;"></span>
                    <?php esc_html_e('Importar Todos los Clientes', 'sapwoo'); ?>
                </button>
            </div>

            <div class="sapwc-progress-wrapper" id="customers-progress-wrapper" style="display:none;">
                <div class="sapwc-progress-bar">
                    <div class="sapwc-progress-fill" id="customers-progress-fill" style="width: 0%;">0%</div>
                </div>
                <div class="sapwc-progress-log" id="customers-progress-log"></div>
            </div>

            <!-- DataTable de clientes -->
            <h3 style="margin-top: 2em;"><?php esc_html_e('Clientes en SAP', 'sapwoo'); ?></h3>
            <input type="text" id="custom-sap-search" placeholder="<?php esc_attr_e('Buscar cliente...', 'sapwoo'); ?>" style="margin-bottom:10px;padding:6px;width:100%;max-width:300px;">
            <button class="button" id="sapwc-customers-table-button" style="margin-left: 10px;">
                <span class="dashicons dashicons-update" style="font-family:dashicons;vertical-align:middle;"></span> <?php esc_html_e('Recargar', 'sapwoo'); ?>
            </button>

            <table id="sapwc-customers-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('CardCode', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Nombre', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('CIF', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Email', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Dirección', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Estado', 'sapwoo'); ?></th>
                        <th><?php esc_html_e('Acción', 'sapwoo'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <!-- Importación Selectiva de Clientes -->
            <div class="sapwc-selective-section" style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                <div class="sapwc-section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <h3 style="margin:0;"><span class="dashicons dashicons-filter" style="font-family:dashicons;"></span> <?php esc_html_e('Importación Selectiva - Clientes Pendientes', 'sapwoo'); ?></h3>
                    <div class="sapwc-section-actions">
                        <button id="sapwc-load-pending-customers" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="font-family:dashicons;vertical-align:middle;"></span>
                            <?php esc_html_e('Cargar Pendientes', 'sapwoo'); ?>
                        </button>
                        <button id="sapwc-import-selected-customers" class="button" disabled>
                            <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                            <?php esc_html_e('Importar Seleccionados', 'sapwoo'); ?>
                            <span class="sapwc-selected-count">(0)</span>
                        </button>
                    </div>
                </div>
                <p class="description"><?php esc_html_e('Clientes de SAP que todavía no tienen usuario en WooCommerce.', 'sapwoo'); ?></p>

                <div class="sapwc-loading-indicator" id="customers-pending-loading" style="display:none;">
                    <span class="spinner is-active"></span> <?php esc_html_e('Consultando SAP...', 'sapwoo'); ?>
                </div>
                <div class="sapwc-results-info" id="customers-results-info" style="display:none;">
                    <span class="dashicons dashicons-info" style="font-family:dashicons;"></span>
                    <span id="customers-count-text"></span>
                </div>
                <table id="sapwc-pending-customers-table" class="wp-list-table widefat fixed striped" style="display:none;">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="sapwc-select-all-customers"></th>
                            <th><?php esc_html_e('CardCode', 'sapwoo'); ?></th>
                            <th><?php esc_html_e('Nombre', 'sapwoo'); ?></th>
                            <th><?php esc_html_e('Email', 'sapwoo'); ?></th>
                            <th style="width:180px;"><?php esc_html_e('Acciones', 'sapwoo'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="sapwc-import-progress" id="customers-selective-progress" style="display:none;">
                    <div class="sapwc-progress-bar">
                        <div class="sapwc-progress-fill" id="customers-selective-fill">0%</div>
                    </div>
                    <div class="sapwc-progress-log" id="customers-selective-log"></div>
                </div>
            </div>
        </div>

        <?php self::render_customers_scripts(); ?>
        <?php
    }

    // =========================================================================
    // ESTILOS COMPARTIDOS
    // =========================================================================
    private static function render_shared_styles()
    {
        ?>
        <style>
            .sapwc-stats-grid {
                display: flex;
                gap: 16px;
                margin: 20px 0;
                flex-wrap: wrap;
            }
            .sapwc-stat-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 20px 28px;
                text-align: center;
                min-width: 160px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }
            .sapwc-stat-number {
                display: block;
                font-size: 32px;
                font-weight: 700;
                color: #2271b1;
                line-height: 1.2;
            }
            .sapwc-stat-number.sapwc-stat-date {
                font-size: 16px;
                color: #555;
            }
            .sapwc-stat-label {
                display: block;
                font-size: 13px;
                color: #666;
                margin-top: 6px;
            }
            .sapwc-import-options {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .sapwc-import-options h3 {
                margin-top: 0;
                color: #2271b1;
            }
            .sapwc-import-options label {
                display: inline-block;
                margin: 6px 0;
                font-size: 14px;
            }
            .sapwc-import-controls {
                margin: 20px 0;
                display: flex;
                gap: 12px;
                align-items: center;
            }
            .sapwc-progress-wrapper {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .sapwc-progress-bar {
                height: 32px;
                background: #e5e5e5;
                border-radius: 16px;
                overflow: hidden;
                position: relative;
            }
            .sapwc-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #2271b1, #135e96);
                border-radius: 16px;
                transition: width 0.4s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-weight: 600;
                font-size: 14px;
                min-width: 40px;
            }
            .sapwc-progress-fill.sapwc-done {
                background: linear-gradient(90deg, #46b450, #2e7d32);
            }
            .sapwc-progress-fill.sapwc-error {
                background: linear-gradient(90deg, #dc3232, #a00);
            }
            .sapwc-progress-log {
                margin-top: 16px;
                max-height: 250px;
                overflow-y: auto;
                font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
                font-size: 12px;
                line-height: 1.8;
                background: #f7f7f7;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 12px;
            }
            .sapwc-progress-log .log-success { color: #2e7d32; }
            .sapwc-progress-log .log-error { color: #c62828; }
            .sapwc-progress-log .log-info { color: #1565c0; }
            .sapwc-progress-log .log-warn { color: #e65100; }

            .sapwc-import-section {
                max-width: 900px;
            }

            /* DataTable styles */
            #sapwc-customers-table { margin-top: 10px; }
            .nav-tab .dashicons { vertical-align: middle; margin-right: 4px; }

            /* Modal de Preview */
            .sapwc-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .sapwc-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.6);
            }
            .sapwc-modal-content {
                position: relative;
                background: #fff;
                border-radius: 8px;
                max-width: 900px;
                width: 95%;
                max-height: 85vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            }
            .sapwc-modal-header {
                padding: 16px 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f7f7f7;
            }
            .sapwc-modal-header h2 {
                margin: 0;
                font-size: 18px;
            }
            .sapwc-modal-close {
                background: none;
                border: none;
                font-size: 28px;
                cursor: pointer;
                color: #666;
                padding: 0 8px;
            }
            .sapwc-modal-close:hover {
                color: #d63638;
            }
            .sapwc-modal-body {
                padding: 20px;
                overflow-y: auto;
                flex: 1;
            }
            .sapwc-modal-footer {
                padding: 16px 20px;
                border-top: 1px solid #ddd;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                background: #f7f7f7;
            }
            .sapwc-preview-columns {
                display: flex;
                gap: 20px;
                align-items: flex-start;
            }
            .sapwc-preview-sap,
            .sapwc-preview-woo {
                flex: 1;
            }
            .sapwc-preview-sap h3,
            .sapwc-preview-woo h3 {
                font-size: 14px;
                margin: 0 0 10px;
                color: #2271b1;
            }
            .sapwc-preview-arrow {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px 10px;
                color: #2271b1;
            }
            .sapwc-preview-loading {
                text-align: center;
                padding: 40px;
            }
            .sapwc-preview-loading .spinner {
                float: none;
            }
            .sapwc-preview-status {
                margin-top: 15px;
                padding: 10px;
                border-radius: 4px;
            }
            .sapwc-preview-status.success {
                background: #d4edda;
                color: #155724;
            }
            .sapwc-preview-status.error {
                background: #f8d7da;
                color: #721c24;
            }

            /* Selective import styles */
            .sapwc-loading-indicator {
                padding: 20px;
                text-align: center;
                background: #f7f7f7;
                border-radius: 4px;
                margin: 10px 0;
            }
            .sapwc-loading-indicator .spinner {
                float: none;
                margin-right: 8px;
            }
            .sapwc-results-info {
                padding: 12px 16px;
                background: #e7f3fe;
                border-left: 4px solid #2271b1;
                margin: 10px 0;
            }
            .sapwc-results-info .dashicons {
                color: #2271b1;
                margin-right: 8px;
            }
            .sapwc-import-progress {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                margin-top: 15px;
            }
            #sapwc-pending-products-table .check-column,
            #sapwc-pending-categories-table .check-column,
            #sapwc-pending-customers-table .check-column {
                width: 40px;
            }
            .sapwc-row-imported {
                background: #d4edda !important;
            }
            .sapwc-row-imported td {
                color: #155724;
            }
            @media (max-width: 782px) {
                .sapwc-preview-columns {
                    flex-direction: column;
                }
                .sapwc-preview-arrow {
                    transform: rotate(90deg);
                    padding: 10px;
                }
            }
        </style>
        <?php
    }

    // =========================================================================
    // SCRIPTS COMPARTIDOS (BATCH ENGINE)
    // =========================================================================
    private static function render_shared_scripts()
    {
        ?>
        <script>
        jQuery(document).ready(function($) {

            // ==============================================================
            // BATCH ENGINE: importador asíncrono genérico
            // ==============================================================
            class BatchImporter {
                constructor(options) {
                    this.action        = options.action;          // AJAX action
                    this.btnStart      = $(options.btnStart);
                    this.btnStop       = options.btnStop ? $(options.btnStop) : null;
                    this.progressWrap  = $(options.progressWrap);
                    this.progressFill  = $(options.progressFill);
                    this.progressLog   = $(options.progressLog);
                    this._extraDataFn  = typeof options.extraData === 'function' ? options.extraData : null;
                    this.extraData     = this._extraDataFn ? {} : (options.extraData || {});
                    this.batchSize     = options.batchSize || 20;
                    this.onComplete    = options.onComplete || function(){};

                    this.running  = false;
                    this.stopped  = false;
                    this.skip     = 0;
                    this.totals   = { created: 0, updated: 0, skipped: 0, linked: 0, errors: 0, processed: 0 };

                    this.btnStart.on('click', () => this.start());
                    if (this.btnStop) {
                        this.btnStop.on('click', () => this.stop());
                    }
                }

                start() {
                    if (this.running) return;
                    this.running = true;
                    this.stopped = false;
                    this.skip    = 0;
                    this.totals  = { created: 0, updated: 0, skipped: 0, linked: 0, errors: 0, processed: 0 };

                    this.btnStart.prop('disabled', true).text('Importando...');
                    if (this.btnStop) this.btnStop.show();
                    this.progressWrap.slideDown();
                    this.progressFill.css('width', '0%').text('0%').removeClass('sapwc-done sapwc-error');
                    this.progressLog.empty();

                    this.log('info', 'Iniciando importación...');
                    this.nextBatch();
                }

                stop() {
                    this.stopped = true;
                    this.log('warn', 'Importación pausada por el usuario.');
                    this.finish(false);
                }

                nextBatch() {
                    if (this.stopped) return;

                    const data = {
                        action: this.action,
                        nonce: sapwc_ajax.nonce,
                        skip: this.skip,
                        batch: this.batchSize,
                        ...(this._extraDataFn ? this._extraDataFn() : this.extraData)
                    };

                    $.post(sapwc_ajax.ajax_url, data)
                        .done((res) => {
                            if (!res.success) {
                                this.log('error', res.data?.message || 'Error desconocido');
                                this.finish(true);
                                return;
                            }

                            const d = res.data;
                            this.totals.created  += d.created || 0;
                            this.totals.updated  += d.updated || 0;
                            this.totals.skipped  += d.skipped || 0;
                            this.totals.linked   += d.linked || 0;
                            this.totals.errors   += d.errors || 0;
                            this.totals.processed += d.batch_size || d.total_fetched || 0;

                            // Progress estimation (we don't know total, use batch heuristic)
                            if (!d.has_more) {
                                this.updateProgress(100);
                            } else {
                                // Estimate based on skip position
                                const est = Math.min(90, Math.round((this.skip / (this.skip + this.batchSize * 3)) * 100));
                                this.updateProgress(est);
                            }

                            this.log('success', d.message || `Lote procesado (offset ${this.skip})`);

                            if (d.has_more && !this.stopped) {
                                this.skip = d.next_skip;
                                // Small delay to be friendly to the server
                                setTimeout(() => this.nextBatch(), 200);
                            } else {
                                this.finish(false);
                            }
                        })
                        .fail((xhr) => {
                            this.log('error', 'Error de red: ' + (xhr.statusText || 'timeout'));
                            this.finish(true);
                        });
                }

                updateProgress(pct) {
                    this.progressFill.css('width', pct + '%').text(pct + '%');
                }

                log(type, msg) {
                    const ts = new Date().toLocaleTimeString();
                    this.progressLog.append(`<div class="log-${type}">[${ts}] ${msg}</div>`);
                    this.progressLog.scrollTop(this.progressLog[0].scrollHeight);
                }

                finish(hasError) {
                    this.running = false;

                    if (hasError) {
                        this.progressFill.addClass('sapwc-error');
                    } else {
                        this.updateProgress(100);
                        this.progressFill.addClass('sapwc-done');
                    }

                    const summary = `Completado: ${this.totals.created} creados, ${this.totals.updated} actualizados, ${this.totals.skipped} omitidos, ${this.totals.linked || 0} vinculados, ${this.totals.errors} errores`;
                    this.log(hasError ? 'error' : 'success', summary);

                    this.btnStart.prop('disabled', false).html(this.btnStart.data('original-text') || 'Importar');
                    if (this.btnStop) this.btnStop.hide();

                    this.onComplete(this.totals);
                }
            }

            // ==============================================================
            // PRODUCTOS
            // ==============================================================
            const $prodBtn = $('#sapwc-import-products-btn');
            $prodBtn.data('original-text', $prodBtn.html());

            new BatchImporter({
                action: 'sapwc_import_products_batch',
                btnStart: '#sapwc-import-products-btn',
                btnStop: '#sapwc-stop-products-btn',
                progressWrap: '#products-progress-wrapper',
                progressFill: '#products-progress-fill',
                progressLog: '#products-progress-log',
                batchSize: 20,
                extraData: function() {
                    return {
                        update_existing:  $('#sapwc-opt-update-existing').is(':checked') ? '1' : '0',
                        import_prices:    $('#sapwc-opt-import-prices').is(':checked') ? '1' : '0',
                        import_stock:     $('#sapwc-opt-import-stock').is(':checked') ? '1' : '0',
                        assign_category:  $('#sapwc-opt-assign-category').is(':checked') ? '1' : '0',
                        default_status:   $('#sapwc-opt-default-status').val()
                    };
                },
                onComplete: function(totals) {
                    $('#stat-products-imported').text(
                        parseInt($('#stat-products-imported').text() || 0) + totals.created
                    );
                }
            });

            // ==============================================================
            // CATEGORÍAS
            // ==============================================================
            const $catBtn = $('#sapwc-import-categories-btn');
            $catBtn.data('original-text', $catBtn.html());

            new BatchImporter({
                action: 'sapwc_import_categories_batch',
                btnStart: '#sapwc-import-categories-btn',
                progressWrap: '#categories-progress-wrapper',
                progressFill: '#categories-progress-fill',
                progressLog: '#categories-progress-log',
                batchSize: 50,
                onComplete: function(totals) {
                    $('#stat-categories-imported').text(
                        parseInt($('#stat-categories-imported').text() || 0) + totals.created
                    );
                }
            });

            // Preview categories
            $('#sapwc-preview-categories-btn').on('click', function() {
                const $btn = $(this);
                const $preview = $('#sapwc-categories-preview');

                $btn.prop('disabled', true).text('Cargando...');

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_preview_categories',
                    nonce: sapwc_ajax.nonce
                }).done(function(res) {
                    if (res.success) {
                        const $tbody = $('#sapwc-categories-table tbody');
                        $tbody.empty();

                        res.data.items.forEach(function(item) {
                            const status = item.exists_in_woo
                                ? '<span class="dashicons dashicons-yes-alt" style="font-family:dashicons;color:green;"></span> Importada'
                                : '<span style="color:#999;">–</span>';

                            $tbody.append(`<tr>
                                <td>${item.Number}</td>
                                <td><strong>${$('<span>').text(item.GroupName).html()}</strong></td>
                                <td>${status}</td>
                            </tr>`);
                        });

                        $preview.slideDown();
                    } else {
                        alert(res.data?.message || 'Error');
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('Previsualizar Categorías de SAP');
                });
            });

            // ==============================================================
            // CLIENTES (batch import)
            // ==============================================================
            const $custBtn = $('#sapwc-import-customers-btn');
            $custBtn.data('original-text', $custBtn.html());

            $('#sapwc-import-customers-btn').on('click', function() {
                const $btn = $(this);
                const $wrap = $('#customers-progress-wrapper');
                const $fill = $('#customers-progress-fill');
                const $log  = $('#customers-progress-log');

                $btn.prop('disabled', true).text('Importando...');
                $wrap.slideDown();
                $fill.css('width', '30%').text('Procesando...').removeClass('sapwc-done sapwc-error');
                $log.empty();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_import_all_customers_v2',
                    nonce: sapwc_ajax.nonce
                }).done(function(res) {
                    $fill.css('width', '100%');
                    if (res.success) {
                        const d = res.data;
                        $fill.text('100%').addClass('sapwc-done');
                        const ts = new Date().toLocaleTimeString();
                        $log.append(`<div class="log-success">[${ts}] ${d.message}</div>`);
                        $('#stat-customers-imported').text(
                            parseInt($('#stat-customers-imported').text() || 0) + (d.imported || 0)
                        );
                        if (d.last_sync) {
                            $('#stat-customers-lastsync').text(d.last_sync);
                        }
                    } else {
                        $fill.text('Error').addClass('sapwc-error');
                        const ts = new Date().toLocaleTimeString();
                        $log.append(`<div class="log-error">[${ts}] ${res.data?.message || 'Error'}</div>`);
                    }
                }).fail(function() {
                    $fill.css('width', '100%').text('Error').addClass('sapwc-error');
                }).always(function() {
                    $btn.prop('disabled', false).html($custBtn.data('original-text'));
                });
            });

        });
        </script>
        <?php
    }

    // =========================================================================
    // SCRIPTS ESPECÍFICOS CLIENTES (DataTable)
    // =========================================================================
    private static function render_customers_scripts()
    {
        ?>
        <script>
        jQuery(document).ready(function($) {
            if (!$.fn.DataTable) return; // Safety check

            const table = $('#sapwc-customers-table').DataTable({
                serverSide: true,
                processing: true,
                searching: false,
                ajax: {
                    url: sapwc_ajax.ajax_url,
                    type: 'POST',
                    data: function(d) {
                        d.action = 'sapwc_get_sap_customers_dt';
                        d.nonce = sapwc_ajax.nonce;
                        d.search_value = $('#custom-sap-search').val();
                        return d;
                    }
                },
                columns: [
                    { data: 'CardCode' },
                    { data: 'CardName', render: function(data) { return data; } },
                    { data: 'FederalTaxID' },
                    { data: 'EmailAddress' },
                    { data: 'Address' },
                    {
                        data: 'is_imported',
                        render: function(isImported) {
                            return isImported ? '<span class="dashicons dashicons-yes-alt" style="font-family:dashicons;color:green;"></span> Importado' : '–';
                        },
                        orderable: false, searchable: false
                    },
                    {
                        data: 'CardCode',
                        render: function(data) {
                            return `<button class="button sapwc-import-customer" data-code="${$('<div>').text(data).html()}"><span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span> Importar</button>`;
                        },
                        orderable: false, searchable: false
                    }
                ],
                pageLength: 10,
                language: {
                    processing: "Cargando...",
                    lengthMenu: "Mostrar _MENU_ clientes",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ clientes",
                    paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
                }
            });

            $('#custom-sap-search').on('change', function() { table.ajax.reload(); });
            $('#custom-sap-search').val('');

            $('#sapwc-customers-table-button').on('click', function() {
                $('#custom-sap-search').val('');
                table.ajax.reload();
            });

            $(document).on('click', '.sapwc-import-customer', function() {
                const $btn = $(this);
                const cardCode = $btn.data('code');

                $btn.prop('disabled', true).text('Importando...');

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_import_single_customer',
                    nonce: sapwc_ajax.nonce,
                    cardcode: cardCode
                }, function(res) {
                    if (res.success) {
                        $btn.replaceWith('<span class="dashicons dashicons-yes-alt" style="font-family:dashicons;color:green;"></span> Importado');
                    } else {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span> Importar');
                        alert('Error: ' + res.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    // =========================================================================
    // SCRIPTS IMPORTACIÓN SELECTIVA
    // =========================================================================
    private static function render_selective_scripts()
    {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Estado actual del modal
            let currentPreviewItem = null;
            let currentPreviewType = null;

            // ==============================================================
            // MODAL PREVIEW
            // ==============================================================
            const $modal = $('#sapwc-preview-modal');
            const $modalTitle = $('#sapwc-modal-title');
            const $modalLoading = $('.sapwc-preview-loading');
            const $modalContent = $('.sapwc-preview-content');
            const $modalStatus = $('#sapwc-preview-status');
            const $sapTable = $('#sapwc-preview-sap-table tbody');
            const $wooTable = $('#sapwc-preview-woo-table tbody');

            function openModal(title) {
                $modalTitle.text(title);
                $modalLoading.show();
                $modalContent.hide();
                $modalStatus.hide().removeClass('success error').empty();
                $sapTable.empty();
                $wooTable.empty();
                $modal.fadeIn(200);
            }

            function closeModal() {
                $modal.fadeOut(200);
                currentPreviewItem = null;
                currentPreviewType = null;
            }

            function showPreviewData(sapData, wooMapping) {
                $sapTable.empty();
                // sapData es un array de objetos con field, label, value
                if (Array.isArray(sapData)) {
                    sapData.forEach(item => {
                        if (item.value !== null && item.value !== '' && item.value !== undefined) {
                            const label = item.label || item.field;
                            $sapTable.append(`<tr><td><strong>${$('<span>').text(label).html()}</strong></td><td>${$('<span>').text(item.value).html()}</td></tr>`);
                        }
                    });
                } else {
                    // Formato objeto simple
                    Object.keys(sapData).forEach(key => {
                        const val = sapData[key];
                        if (val !== null && val !== '' && typeof val !== 'object') {
                            $sapTable.append(`<tr><td><strong>${key}</strong></td><td>${$('<span>').text(val).html()}</td></tr>`);
                        }
                    });
                }

                $wooTable.empty();
                // wooMapping es un array de objetos con field/label y source
                wooMapping.forEach(item => {
                    const label = item.label || item.woo_field || item.field;
                    const source = item.source || item.sap_source;
                    $wooTable.append(`<tr><td><strong>${$('<span>').text(label).html()}</strong></td><td>${$('<span>').text(source).html()}</td></tr>`);
                });

                $modalLoading.hide();
                $modalContent.show();
            }

            // Cerrar modal
            $('.sapwc-modal-close, #sapwc-modal-cancel, .sapwc-modal-overlay').on('click', closeModal);
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') closeModal();
            });

            // Importar desde modal
            $('#sapwc-modal-import').on('click', function() {
                if (!currentPreviewItem || !currentPreviewType) return;

                const $btn = $(this);
                $btn.prop('disabled', true).text('Importando...');
                $modalStatus.hide();

                let action, dataKey;
                if (currentPreviewType === 'product') {
                    action = 'sapwc_import_single_product';
                    dataKey = 'item_code';
                } else if (currentPreviewType === 'category') {
                    action = 'sapwc_import_single_category';
                    dataKey = 'group_number';
                } else {
                    action = 'sapwc_import_single_customer';
                    dataKey = 'cardcode';
                }

                const postData = {
                    action: action,
                    nonce: sapwc_ajax.nonce
                };
                postData[dataKey] = currentPreviewItem;

                $.post(sapwc_ajax.ajax_url, postData).done(function(res) {
                    if (res.success) {
                        $modalStatus.addClass('success').text('✓ ' + (res.data?.message || 'Importado correctamente')).show();
                        // Marcar fila como importada
                        const selector = currentPreviewType === 'product' ? '#sapwc-pending-products-table' :
                                          currentPreviewType === 'category' ? '#sapwc-pending-categories-table' :
                                          '#sapwc-pending-customers-table';
                        $(selector + ' tr[data-id="' + currentPreviewItem + '"]').addClass('sapwc-row-imported');
                        setTimeout(closeModal, 1500);
                    } else {
                        $modalStatus.addClass('error').text('✗ ' + (res.data?.message || 'Error al importar')).show();
                    }
                }).fail(function() {
                    $modalStatus.addClass('error').text('✗ Error de conexión').show();
                }).always(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span> Importar Ahora');
                });
            });

            // ==============================================================
            // PRODUCTOS PENDIENTES
            // ==============================================================
            $('#sapwc-load-pending-products').on('click', function() {
                const $btn = $(this);
                const $loading = $('#products-pending-loading');
                const $info = $('#products-results-info');
                const $table = $('#sapwc-pending-products-table');

                $btn.prop('disabled', true);
                $loading.show();
                $info.hide();
                $table.hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_get_pending_products',
                    nonce: sapwc_ajax.nonce
                }).done(function(res) {
                    if (res.success) {
                        const items = res.data.items || [];
                        $('#products-count-text').text(items.length + ' productos pendientes de importar');
                        $info.show();

                        const $tbody = $table.find('tbody');
                        $tbody.empty();

                        items.forEach(function(item) {
                            $tbody.append(`
                                <tr data-id="${item.ItemCode}">
                                    <td><input type="checkbox" class="sapwc-select-product" value="${item.ItemCode}"></td>
                                    <td><strong>${$('<span>').text(item.ItemCode).html()}</strong></td>
                                    <td>${$('<span>').text(item.ItemName).html()}</td>
                                    <td>${item.ItemsGroupCode || '-'}</td>
                                    <td>
                                        <button class="button sapwc-preview-product" data-code="${item.ItemCode}">
                                            <span class="dashicons dashicons-visibility" style="font-family:dashicons;vertical-align:middle;"></span> Ver
                                        </button>
                                        <button class="button button-primary sapwc-import-single-product" data-code="${item.ItemCode}">
                                            <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });

                        $table.show();
                    } else {
                        alert(res.data?.message || 'Error al cargar productos');
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
                currentPreviewItem = code;
                currentPreviewType = 'product';
                openModal('Vista Previa: Producto ' + code);

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_preview_product',
                    nonce: sapwc_ajax.nonce,
                    item_code: code
                }).done(function(res) {
                    if (res.success) {
                        showPreviewData(res.data.sap_data, res.data.woo_mapping);
                    } else {
                        $modalLoading.hide();
                        $modalStatus.addClass('error').text(res.data?.message || 'Error').show();
                    }
                });
            });

            // Importar producto individual
            $(document).on('click', '.sapwc-import-single-product', function() {
                const $btn = $(this);
                const code = $btn.data('code');
                $btn.prop('disabled', true);

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_import_single_product',
                    nonce: sapwc_ajax.nonce,
                    item_code: code
                }).done(function(res) {
                    if (res.success) {
                        $btn.closest('tr').addClass('sapwc-row-imported');
                        $btn.replaceWith('<span class="dashicons dashicons-yes-alt" style="font-family:dashicons;color:green;"></span>');
                    } else {
                        $btn.prop('disabled', false);
                        alert(res.data?.message || 'Error');
                    }
                });
            });

            // Select all productos
            $('#sapwc-select-all-products').on('change', function() {
                $('.sapwc-select-product').prop('checked', $(this).is(':checked'));
                updateSelectedCount('products');
            });
            $(document).on('change', '.sapwc-select-product', function() {
                updateSelectedCount('products');
            });

            // ==============================================================
            // CATEGORÍAS PENDIENTES
            // ==============================================================
            $('#sapwc-load-pending-categories').on('click', function() {
                const $btn = $(this);
                const $loading = $('#categories-pending-loading');
                const $info = $('#categories-results-info');
                const $table = $('#sapwc-pending-categories-table');

                $btn.prop('disabled', true);
                $loading.show();
                $info.hide();
                $table.hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_get_pending_categories',
                    nonce: sapwc_ajax.nonce
                }).done(function(res) {
                    if (res.success) {
                        const items = res.data.items || [];
                        $('#categories-count-text').text(items.length + ' categorías pendientes de importar');
                        $info.show();

                        const $tbody = $table.find('tbody');
                        $tbody.empty();

                        items.forEach(function(item) {
                            $tbody.append(`
                                <tr data-id="${item.Number}">
                                    <td><input type="checkbox" class="sapwc-select-category" value="${item.Number}"></td>
                                    <td><strong>${item.Number}</strong></td>
                                    <td>${$('<span>').text(item.GroupName).html()}</td>
                                    <td>
                                        <button class="button sapwc-preview-category" data-number="${item.Number}">
                                            <span class="dashicons dashicons-visibility" style="font-family:dashicons;vertical-align:middle;"></span> Ver
                                        </button>
                                        <button class="button button-primary sapwc-import-single-category" data-number="${item.Number}">
                                            <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });

                        $table.show();
                    } else {
                        alert(res.data?.message || 'Error al cargar categorías');
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
                currentPreviewItem = num;
                currentPreviewType = 'category';
                openModal('Vista Previa: Categoría ' + num);

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_preview_category',
                    nonce: sapwc_ajax.nonce,
                    group_number: num
                }).done(function(res) {
                    if (res.success) {
                        showPreviewData(res.data.sap_data, res.data.woo_mapping);
                    } else {
                        $modalLoading.hide();
                        $modalStatus.addClass('error').text(res.data?.message || 'Error').show();
                    }
                });
            });

            // Importar categoría individual
            $(document).on('click', '.sapwc-import-single-category', function() {
                const $btn = $(this);
                const num = $btn.data('number');
                $btn.prop('disabled', true);

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_import_single_category',
                    nonce: sapwc_ajax.nonce,
                    group_number: num
                }).done(function(res) {
                    if (res.success) {
                        $btn.closest('tr').addClass('sapwc-row-imported');
                        $btn.replaceWith('<span class="dashicons dashicons-yes-alt" style="font-family:dashicons;color:green;"></span>');
                    } else {
                        $btn.prop('disabled', false);
                        alert(res.data?.message || 'Error');
                    }
                });
            });

            // Select all categorías
            $('#sapwc-select-all-categories').on('change', function() {
                $('.sapwc-select-category').prop('checked', $(this).is(':checked'));
                updateSelectedCount('categories');
            });
            $(document).on('change', '.sapwc-select-category', function() {
                updateSelectedCount('categories');
            });

            // ==============================================================
            // CLIENTES PENDIENTES
            // ==============================================================
            $('#sapwc-load-pending-customers').on('click', function() {
                const $btn = $(this);
                const $loading = $('#customers-pending-loading');
                const $info = $('#customers-results-info');
                const $table = $('#sapwc-pending-customers-table');

                $btn.prop('disabled', true);
                $loading.show();
                $info.hide();
                $table.hide();

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_get_pending_customers',
                    nonce: sapwc_ajax.nonce
                }).done(function(res) {
                    if (res.success) {
                        const items = res.data.items || [];
                        $('#customers-count-text').text(items.length + ' clientes pendientes de importar');
                        $info.show();

                        const $tbody = $table.find('tbody');
                        $tbody.empty();

                        items.forEach(function(item) {
                            $tbody.append(`
                                <tr data-id="${item.CardCode}">
                                    <td><input type="checkbox" class="sapwc-select-customer" value="${item.CardCode}"></td>
                                    <td><strong>${$('<span>').text(item.CardCode).html()}</strong></td>
                                    <td>${$('<span>').text(item.CardName || '').html()}</td>
                                    <td>${$('<span>').text(item.EmailAddress || '-').html()}</td>
                                    <td>
                                        <button class="button sapwc-preview-customer" data-code="${item.CardCode}">
                                            <span class="dashicons dashicons-visibility" style="font-family:dashicons;vertical-align:middle;"></span> Ver
                                        </button>
                                        <button class="button button-primary sapwc-import-single-customer-btn" data-code="${item.CardCode}">
                                            <span class="dashicons dashicons-download" style="font-family:dashicons;vertical-align:middle;"></span>
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });

                        $table.show();
                    } else {
                        alert(res.data?.message || 'Error al cargar clientes');
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
                currentPreviewItem = code;
                currentPreviewType = 'customer';
                openModal('Vista Previa: Cliente ' + code);

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_preview_customer',
                    nonce: sapwc_ajax.nonce,
                    cardcode: code
                }).done(function(res) {
                    if (res.success) {
                        showPreviewData(res.data.sap_data, res.data.woo_mapping);
                    } else {
                        $modalLoading.hide();
                        $modalStatus.addClass('error').text(res.data?.message || 'Error').show();
                    }
                });
            });

            // Importar cliente individual desde tabla pendientes
            $(document).on('click', '.sapwc-import-single-customer-btn', function() {
                const $btn = $(this);
                const code = $btn.data('code');
                $btn.prop('disabled', true);

                $.post(sapwc_ajax.ajax_url, {
                    action: 'sapwc_import_single_customer',
                    nonce: sapwc_ajax.nonce,
                    cardcode: code
                }).done(function(res) {
                    if (res.success) {
                        $btn.closest('tr').addClass('sapwc-row-imported');
                        $btn.replaceWith('<span class="dashicons dashicons-yes-alt" style="font-family:dashicons;color:green;"></span>');
                    } else {
                        $btn.prop('disabled', false);
                        alert(res.data?.message || res.data || 'Error');
                    }
                });
            });

            // Select all clientes
            $('#sapwc-select-all-customers').on('change', function() {
                $('.sapwc-select-customer').prop('checked', $(this).is(':checked'));
                updateSelectedCount('customers');
            });
            $(document).on('change', '.sapwc-select-customer', function() {
                updateSelectedCount('customers');
            });

            // ==============================================================
            // IMPORTAR SELECCIONADOS (bulk)
            // ==============================================================
            function updateSelectedCount(type) {
                const count = $(`.sapwc-select-${type.slice(0,-1)}:checked`).length;
                $(`#sapwc-import-selected-${type} .sapwc-selected-count`).text('(' + count + ')');
                $(`#sapwc-import-selected-${type}`).prop('disabled', count === 0);
            }

            // Importar productos seleccionados
            $('#sapwc-import-selected-products').on('click', function() {
                const codes = [];
                $('.sapwc-select-product:checked').each(function() {
                    codes.push($(this).val());
                });
                if (codes.length === 0) return;

                importBulk('products', codes, 'sapwc_import_single_product', 'item_code');
            });

            // Importar categorías seleccionadas
            $('#sapwc-import-selected-categories').on('click', function() {
                const nums = [];
                $('.sapwc-select-category:checked').each(function() {
                    nums.push($(this).val());
                });
                if (nums.length === 0) return;

                importBulk('categories', nums, 'sapwc_import_single_category', 'group_number');
            });

            // Importar clientes seleccionados
            $('#sapwc-import-selected-customers').on('click', function() {
                const codes = [];
                $('.sapwc-select-customer:checked').each(function() {
                    codes.push($(this).val());
                });
                if (codes.length === 0) return;

                importBulk('customers', codes, 'sapwc_import_single_customer', 'cardcode');
            });

            function importBulk(type, items, action, dataKey) {
                const $progress = $(`#${type}-selective-progress`);
                const $fill = $(`#${type}-selective-fill`);
                const $log = $(`#${type}-selective-log`);

                $progress.show();
                $fill.css('width', '0%').text('0%');
                $log.empty();

                let current = 0;
                const total = items.length;

                function importNext() {
                    if (current >= total) {
                        $fill.css('width', '100%').text('100%').addClass('sapwc-done');
                        const ts = new Date().toLocaleTimeString();
                        $log.append(`<div class="log-success">[${ts}] Importación completada: ${total} elementos</div>`);
                        return;
                    }

                    const item = items[current];
                    const postData = { action: action, nonce: sapwc_ajax.nonce };
                    postData[dataKey] = item;

                    $.post(sapwc_ajax.ajax_url, postData).done(function(res) {
                        const ts = new Date().toLocaleTimeString();
                        if (res.success) {
                            $(`#sapwc-pending-${type}-table tr[data-id="${item}"]`).addClass('sapwc-row-imported');
                            $log.append(`<div class="log-success">[${ts}] ✓ ${item}</div>`);
                        } else {
                            $log.append(`<div class="log-error">[${ts}] ✗ ${item}: ${res.data?.message || res.data || 'Error'}</div>`);
                        }
                    }).fail(function() {
                        const ts = new Date().toLocaleTimeString();
                        $log.append(`<div class="log-error">[${ts}] ✗ ${item}: Error de conexión</div>`);
                    }).always(function() {
                        current++;
                        const pct = Math.round((current / total) * 100);
                        $fill.css('width', pct + '%').text(pct + '%');
                        $log.scrollTop($log[0].scrollHeight);
                        setTimeout(importNext, 300); // Delay para no saturar
                    });
                }

                importNext();
            }
        });
        </script>
        <?php
    }
}

// =============================================================================
// AJAX ENDPOINTS
// =============================================================================

/**
 * Importación de productos por lotes
 */
add_action('wp_ajax_sapwc_import_products_batch', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $skip  = intval($_POST['skip'] ?? 0);
    $batch = intval($_POST['batch'] ?? 20);
    $batch = min($batch, 50); // Max 50 por lote

    $options = [
        'update_existing'  => ($_POST['update_existing'] ?? '1') === '1',
        'import_prices'    => ($_POST['import_prices'] ?? '1') === '1',
        'import_stock'     => ($_POST['import_stock'] ?? '1') === '1',
        'assign_category'  => ($_POST['assign_category'] ?? '1') === '1',
        'default_status'   => sanitize_text_field($_POST['default_status'] ?? 'draft'),
    ];

    $result = SAPWC_Product_Sync::import_batch($skip, $batch, $options);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

/**
 * Importación de categorías por lotes
 */
add_action('wp_ajax_sapwc_import_categories_batch', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $skip  = intval($_POST['skip'] ?? 0);
    $batch = intval($_POST['batch'] ?? 50);

    $result = SAPWC_Category_Sync::import_batch($skip, $batch);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

/**
 * Previsualización de categorías SAP
 */
add_action('wp_ajax_sapwc_preview_categories', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    $result = SAPWC_Category_Sync::fetch_all_from_sap();

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    // Enrich with Woo status
    $mapping = SAPWC_Category_Sync::get_mapping();
    foreach ($result['items'] as &$item) {
        $item['exists_in_woo'] = isset($mapping[$item['Number']]);
    }

    wp_send_json_success($result);
});

/**
 * Importación masiva de clientes (v2, para la nueva UI)
 */
add_action('wp_ajax_sapwc_import_all_customers_v2', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Customer_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización de clientes no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Customer_Sync::sync_all_pending();

    if (!empty($result['locked'])) {
        wp_send_json_error(['message' => __('Sincronización ya en curso. Espera a que termine.', 'sapwoo')]);
    }

    wp_send_json_success([
        'message'   => $result['message'] ?? __('Completado', 'sapwoo'),
        'imported'  => $result['imported'] ?? 0,
        'errors'    => $result['errors'] ?? 0,
        'skipped'   => $result['skipped'] ?? 0,
        'last_sync' => get_option('sapwc_customers_last_sync', ''),
    ]);
});

// =============================================================================
// AJAX ENDPOINTS - IMPORTACIÓN SELECTIVA
// =============================================================================

/**
 * Obtener productos pendientes (en SAP pero no en Woo)
 */
add_action('wp_ajax_sapwc_get_pending_products', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Product_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Product_Sync::get_pending_products();

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success(['items' => $result['items'] ?? []]);
});

/**
 * Preview de un producto antes de importar
 */
add_action('wp_ajax_sapwc_preview_product', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $item_code = sanitize_text_field($_POST['item_code'] ?? '');
    if (empty($item_code)) {
        wp_send_json_error(['message' => __('Código de producto requerido.', 'sapwoo')]);
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
 * Importar un producto individual
 */
add_action('wp_ajax_sapwc_import_single_product', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $item_code = sanitize_text_field($_POST['item_code'] ?? '');
    if (empty($item_code)) {
        wp_send_json_error(['message' => __('Código de producto requerido.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Product_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Product_Sync::import_single($item_code);

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success(['message' => $result['message'] ?? __('Producto importado correctamente.', 'sapwoo')]);
});

/**
 * Obtener categorías pendientes (en SAP pero no en Woo)
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

    wp_send_json_success(['items' => $result['items'] ?? []]);
});

/**
 * Preview de una categoría antes de importar
 */
add_action('wp_ajax_sapwc_preview_category', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $group_number = intval($_POST['group_number'] ?? 0);
    if ($group_number <= 0) {
        wp_send_json_error(['message' => __('Número de grupo requerido.', 'sapwoo')]);
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
 * Importar una categoría individual
 */
add_action('wp_ajax_sapwc_import_single_category', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $group_number = intval($_POST['group_number'] ?? 0);
    if ($group_number <= 0) {
        wp_send_json_error(['message' => __('Número de grupo requerido.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Category_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Category_Sync::import_single($group_number);

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success(['message' => $result['message'] ?? __('Categoría importada correctamente.', 'sapwoo')]);
});

/**
 * Obtener clientes pendientes (en SAP pero no en Woo)
 */
add_action('wp_ajax_sapwc_get_pending_customers', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    if (!class_exists('SAPWC_Customer_Sync')) {
        wp_send_json_error(['message' => __('Clase de sincronización no disponible.', 'sapwoo')]);
    }

    $result = SAPWC_Customer_Sync::get_pending_customers();

    if (isset($result['error'])) {
        wp_send_json_error(['message' => $result['error']]);
    }

    wp_send_json_success(['items' => $result['items'] ?? []]);
});

/**
 * Preview de un cliente antes de importar
 */
add_action('wp_ajax_sapwc_preview_customer', function () {
    check_ajax_referer('sapwc_nonce', 'nonce');

    if (!current_user_can('edit_others_shop_orders')) {
        wp_send_json_error(['message' => __('Sin permisos.', 'sapwoo')]);
    }

    $cardcode = sanitize_text_field($_POST['cardcode'] ?? '');
    if (empty($cardcode)) {
        wp_send_json_error(['message' => __('CardCode requerido.', 'sapwoo')]);
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
