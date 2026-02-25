# Estructura del Plugin - SAP Woo Sync

Documentación técnica de la arquitectura del plugin para desarrolladores.

## Árbol de directorios

```
sap-woo/
├── sap-wc-sync.php          # Archivo principal del plugin
├── README.md                 # Documentación principal
├── readme.txt                # Readme formato WordPress.org
├── CHANGELOG.md              # Historial de versiones
├── composer.json             # Dependencias Composer
│
├── admin/                    # Clases del panel de administración
│   ├── class-settings-page.php         # Credenciales SAP
│   ├── class-sync-options-page.php     # Opciones de sincronización
│   ├── class-orders-page.php           # Lista de pedidos WooCommerce
│   ├── class-sap-orders-table.php      # Tabla de pedidos desde SAP
│   ├── class-import-page.php           # Importación unificada (Productos/Categorías/Clientes)
│   ├── class-customers-import-page.php # [Legacy] Importación de clientes
│   ├── class-failed-orders-page.php    # Pedidos fallidos
│   ├── class-logs-page.php             # Registro de logs
│   ├── class-mapping-page.php          # Mapeo de campos Woo ↔ SAP
│   ├── class-extensions-page.php       # Extensiones (logística, estados, etc.)
│   └── class-udf-mapping-page.php      # Mapeo de campos UDF
│
├── includes/                 # Clases de lógica de negocio
│   ├── class-api-client.php            # Cliente HTTP para SAP Service Layer
│   ├── class-sap-sync.php              # Handler de sincronización de pedidos
│   ├── class-product-sync.php          # Sincronización de productos
│   ├── class-category-sync.php         # Sincronización de categorías
│   ├── class-customer-sync.php         # Sincronización de clientes B2B
│   ├── class-welcome-mailer.php        # Emails de bienvenida
│   ├── class-logger.php                # Sistema de logs
│   ├── class-extension-fields.php      # Campos de extensiones
│   └── helper.php                      # Funciones auxiliares
│
├── assets/                   # Recursos estáticos
│   ├── css/
│   │   ├── sapwc-admin.css             # Estilos del admin
│   │   └── sapwc-toggle.css            # Estilos de toggles
│   └── js/
│       └── admin.js                    # JavaScript del admin
│
├── templates/                # Plantillas de emails
│   └── emails/
│       └── customer-welcome.php        # Email de bienvenida B2B
│
├── sql/                      # Esquemas de base de datos
│   └── install.sql                     # Tabla de logs
│
├── docs/                     # Documentación adicional
│   └── INSTALL.md                      # Guía de instalación
│
└── vendor/                   # Dependencias (composer)
    └── yahnis-elsts/
        └── plugin-update-checker/      # Sistema de actualizaciones
```

## Flujo de datos

### Envío de pedidos (WooCommerce → SAP)

```
1. Hook: woocommerce_order_status_processing
   ↓
2. SAPWC_Sync_Handler::send_order($order)
   ↓
3. build_payload_ecommerce() o build_payload_b2b()
   ↓
4. SAPWC_API_Client::post('/Orders', $payload)
   ↓
5. Guardar DocEntry en post_meta
   ↓
6. SAPWC_Logger::log()
```

### Importación de productos (SAP → WooCommerce)

```
1. AJAX: sapwc_import_products_batch
   ↓
2. SAPWC_Product_Sync::import_batch($skip, $limit, $options)
   ↓
3. SAPWC_API_Client::get('/Items?$skip=X&$top=Y')
   ↓
4. Por cada item: create/update WC_Product
   ↓
5. Respuesta JSON con progreso
```

### Sincronización automática de stock

```
1. Cron: sapwc_stock_sync_event
   ↓
2. SAPWC_Product_Sync::sync_stock_batch()
   ↓
3. Para cada producto con SKU:
   - GET /Items('SKU')?$select=ItemWarehouseInfoCollection
   ↓
4. wc_update_product_stock()
```

## Hooks disponibles

### Actions

```php
// Antes de enviar un pedido a SAP
do_action('sapwc_before_send_order', $order, $payload);

// Después de enviar un pedido a SAP
do_action('sapwc_after_send_order', $order, $response, $success);

// Después de importar un producto
do_action('sapwc_after_import_product', $product_id, $sap_item);

// Después de importar un cliente B2B
do_action('sapwc_after_import_customer', $user_id, $bp_data);
```

### Filters

```php
// Modificar el payload antes de enviar a SAP
$payload = apply_filters('sapwc_order_payload', $payload, $order);

// Modificar datos del producto antes de guardar
$product_data = apply_filters('sapwc_product_data', $product_data, $sap_item);

// Modificar la query de SAP para productos
$query = apply_filters('sapwc_items_query', $query);
```

## Opciones de WordPress

| Opción | Descripción |
|--------|-------------|
| `sapwc_connections` | Array de conexiones SAP |
| `sapwc_connection_index` | Índice de conexión activa |
| `sapwc_mode` | Modo: 'ecommerce' o 'b2b' |
| `sapwc_selected_tariff` | Tarifa de precios por defecto |
| `sapwc_tariff_peninsula` | Tarifa para Península |
| `sapwc_tariff_canarias` | Tarifa para Canarias |
| `sapwc_tariff_portugal` | Tarifa para Portugal |
| `sapwc_cardcode_peninsula` | CardCode cliente Península |
| `sapwc_cardcode_canarias` | CardCode cliente Canarias |
| `sapwc_cardcode_portugal` | CardCode cliente Portugal |
| `sapwc_stock_warehouses` | Almacenes para stock (array) |
| `sapwc_auto_send_orders` | Toggle envío automático |
| `sapwc_auto_sync_stock` | Toggle sync automático stock |
| `sapwc_auto_sync_prices` | Toggle sync automático precios |

## Base de datos

### Tabla: `{prefix}sapwc_logs`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint | ID autoincrement |
| order_id | bigint | ID del pedido WooCommerce |
| action | varchar(50) | Tipo de acción |
| status | varchar(20) | success/error/warning |
| message | text | Mensaje detallado |
| docentry | varchar(50) | DocEntry de SAP |
| created_at | datetime | Fecha de creación |

## Campos UDF utilizados

El plugin puede utilizar los siguientes campos definidos por el usuario (UDF) en SAP:

| Campo | Entidad | Uso |
|-------|---------|-----|
| U_ARTES_Portes | Orders | Tipo de portes |
| U_ARTES_Ruta | Orders | Código de ruta |
| U_ARTES_Com | Orders | Nombre comercial |
| U_ARTES_TEL | Orders | Teléfono |
| U_ARTES_Alerta | Orders | Alerta para almacén |
| U_ARTES_Observ | Orders | Observaciones |
| U_ARTES_CantSC | DocumentLines | Cantidad sin cargo |
| U_ARTES_CLIW | BusinessPartners | Marcador cliente web |
| U_DNI | Orders | DNI del cliente |
| U_DRA_Observ_Agencia | Orders | ID para agencia |
| U_DRA_Coment_Alm | Orders | Comentario almacén |

---

*Documentación técnica para desarrolladores - Replanta*
