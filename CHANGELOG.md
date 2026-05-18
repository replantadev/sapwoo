# Changelog

Todos los cambios notables en **SAP Woo Suite** se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Versionado Semántico](https://semver.org/lang/es/).

---
## [2.15.11] - 2026-05-16

### Corregido

- **Error 400 en "Previsualizar categorías" (modo ecommerce)**: `SAPWC_Import_Page` no estaba en la lista de clases de carga forzada (`class_exists()`) del arranque del plugin. Durante peticiones `admin-ajax.php`, el autoloader lazy nunca incluía `class-import-page.php`, así que el hook `wp_ajax_sapwc_preview_categories` no se registraba y WordPress devolvía "0" (HTTP 400). Añadido `class_exists('SAPWC_Import_Page')` junto al resto de clases con handlers AJAX.
- **Admin notice HMAC persistente en instalaciones co-ubicadas**: el aviso de secret por defecto ya no aparece cuando el Control Center está instalado en el mismo sitio (`sapwcc_get_flags_hmac_secret` existe en el mismo proceso). En ese setup comparten BD y no hay auto-registro cliente.

---
## [2.15.10] - 2026-05-15

### Corregido

- **Versión disponible incorrecta en `control/update-check` y `control/update`**: PUC almacena su estado (última versión consultada, timestamp de check) en una opción de base de datos `external_updates-sap-woo-suite`. Si este estado no se resetea, `wp_update_plugins()` no fuerza una nueva consulta a GitHub Releases aunque se borre el transient de core `update_plugins`, devolviendo una versión obsoleta. Ahora ambas operaciones borran primero el estado PUC antes de llamar a `wp_update_plugins()`, garantizando que siempre se consulta el último release real de GitHub.

---
## [2.15.9] - 2026-05-15

### Añadido

- **Control API — `POST /control/update`**: permite al Control Center ejecutar la actualización del plugin en el sitio remoto sin acceso al WP Admin. Usa `Plugin_Upgrader` con `FS_METHOD=direct` para evitar prompts de FTP en contexto REST. Devuelve 409 si ya está en la última versión.
- **Control API — `POST /control/rotate-secret`**: genera un nuevo `sapwc_webhook_secret` criptográficamente seguro y lo devuelve al Control Center para que actualice su copia local automáticamente. El secret antiguo sigue siendo válido para la petición de rotación (la autenticación ocurre antes de la rotación).

### Corregido

- **Notice HMAC inestable en paneles con supresión de admin_notices**: extraído a función nombrada `sapwc_hmac_notice()` para poder re-registrarse tras `remove_all_actions('admin_notices')` en `admin_head`. Ya no se pierde el aviso cuando otros plugins o temas limpian todos los notices.

---
## [2.15.8] - 2026-05-15

### Corregido

- **Silencio total en fallo de re-login SAP (401)**: si la sesión SAP expiraba entre el POST del pedido y el GET del Business Partner, el re-login fallaba sin dejar ninguna traza en los logs (solo se registraba con `WP_DEBUG=true`). Ahora el fallo de re-login siempre escribe en `error_log`, independientemente de WP_DEBUG.
- **Riesgo de sobreescribir BPAddresses con respuesta inesperada**: si SAP devolvía un 401 con body vacío o no-JSON, `request()` retornaba `['raw' => '', 'http_code' => 401]` sin clave `error`. La función `add_shipping_address_to_bp()` no detectaba el error, continuaba con `BPAddresses = []` y habría ejecutado el PATCH borrando todas las direcciones existentes del BP. Añadida validación explícita de `CardCode` en la respuesta antes de proceder; si no está presente se aborta con log de error.

---
## [2.15.7] - 2026-05-15

### Corregido

- **`_sap_address_synced` falso positivo**: la meta se marcaba como `1` antes de ejecutar el PATCH de dirección (al hacer `update_status()`), lo que daba la impresión de que la dirección estaba sincronizada aunque el PATCH hubiera fallado. Ahora solo se establece `1` si `add_shipping_address_to_bp()` devuelve `true`.
- **Excepción silenciosa en PATCH de dirección**: si `add_shipping_address_to_bp()` lanzaba una excepción (p.ej. timeout o error inesperado del cliente SAP), el bloque de éxito no capturaba el error — ni se añadía nota de pedido ni se escribía en los logs SAPWC. Añadido `try/catch Throwable` alrededor de la llamada: ahora siempre se escribe una nota y un log, independientemente del resultado.

---
## [2.15.5] - 2026-05-12

### Corregido

- **BOM UTF-8 en class-sap-sync.php**: el archivo tenía una marca BOM al inicio que provocaba pantalla en blanco ("headers already sent"). Eliminado.

---
## [2.15.4] - 2026-05-12

### Corregido

- **Race condition en BPAddresses (ecommerce)**: cuando dos pedidos del mismo cliente llegaban simultáneamente (p.ej. doble callback de Redsys), ambos leían el mismo array de direcciones SAP y el PATCH del segundo sobreescribía la dirección del primero, borrándola. Se añade un mutex via `add_transient()` (INSERT IGNORE atómico) por CardCode en `add_shipping_address_to_bp()` y `add_shipping_address_to_bp_b2b()`. Si el lock no se adquiere, se registra en log y la nota del pedido advierte del conflicto en vez de afirmar éxito.
- **Descuento como línea AJUSTE en SAP**: los pedidos con cupón WooCommerce generaban una línea `AJUSTE` negativa en SAP en lugar de reflejarse en el campo "Dto Cliente" (DiscPrcnt). `build_payload_ecommerce()` ahora calcula y envía `DiscPrcnt = descuento / bruto_pre_descuento × 100` en la cabecera del documento.
- **Guard en ajuste de redondeo**: `add_rounding_adjustment_if_needed()` no añade la línea AJUSTE si la diferencia supera `max(€0.10, 0.2% del subtotal)`, evitando que descuentos grandes se conviertan en líneas de ajuste incorrectas con código EX.

---
## [2.15.3] - 2026-04-09

### Añadido

- **Panel Plan & Features en Dashboard**: nueva sección visual en el panel de control que muestra el plan activo (Starter/Business/Enterprise), site ID y el estado de cada feature del plan, facilitando la verificación sin acceder al Control Center.

### Corregido

- **Consistencia de plan en health reports**: `SAPWC_Site_Profile::get_profile()` ahora usa `SAPWC_Feature_Flags::get_plan()` (que respeta la jerarquía flags.json > opción local) en lugar de leer directamente `get_option('sapwc_plan')`, eliminando las alertas de inconsistencia de plan entre Control Center y los sitios remotos.

---
## [2.15.2] - 2026-04-01

### Corregido

- **AJAX handler pedidos SAP**: el handler `sapwc_get_sap_orders` estaba registrado en `class-sap-orders-table.php` (carga por autoloader) pero durante `admin-ajax.php` la clase nunca se referenciaba, provocando que WordPress devolviera "0" (HTTP 400). Movido el registro del handler al archivo principal `sap-woo-suite.php` donde se ejecuta siempre.
- **Output buffering en AJAX**: añadido `ob_start()`/`ob_end_clean()` para capturar warnings/notices de otros plugins que corrompian la respuesta JSON, causando "Error de red" en el dashboard.
- **Errores descriptivos**: el handler ahora devuelve el error SAP real (HTTP code + body) en vez del genérico "Error al obtener pedidos".
- **DataTables JS**: inicialización movida dentro del callback `.done()` para evitar crash cuando se ejecutaba antes de tener datos en la tabla.

---
## [2.15.1] - 2026-03-31

### Añadido

- **Control API (5 endpoints REST)**: nuevo `class-control-api.php` con endpoints bajo `/sapwc/v1/control/` para gestión remota desde el Control Center:
  - `GET /control/logs` — consulta del log de sincronización (filtro por nivel y límite)
  - `POST /control/clear-cache` — limpieza de transients SAPWC + WP cache + WC product transients
  - `POST /control/run-cron` — ejecución inmediata de crons permitidos (orders, stock, products, categories)
  - `POST /control/maintenance` — activar/desactivar modo mantenimiento WordPress
  - `GET /control/update-check` — verificar actualizaciones disponibles via PUC
- **Autenticación**: todos los endpoints usan `SAPWC_REST_API::auth()` (header X-SAPWC-Secret)

---
## [2.15.0] - 2026-03-31

### Añadido

- **Plan-based feature gating**: nuevo sistema de planes (Starter / Business / Enterprise) con enforcement tecnico real. Las funcionalidades se bloquean tanto a nivel de UI como en server-side (sanitize_callbacks, cron guards, menu ocultacion).
- **7 plan features controladas**: `multi_warehouse`, `catalog_import`, `b2b_mode`, `multichannel`, `miravia`, `extension_hooks`, `volume_pricing`.
- **Asignacion remota de plan**: el plan se asigna desde `flags.json` (GitHub Pages) con resolucion en 3 niveles: override por sitio > plan del sitio > fallback local.
- **flags.json schema v2**: nueva seccion `plans` con matriz de features por plan + `sites` como objeto con `plan` y `overrides` por site_id.
- **Metodos en SAPWC_Feature_Flags**: `get_plan()`, `is_plan_feature()`, `get_plan_label()`, `get_plan_features()`, constante `PLAN_FEATURES_FALLBACK`.
- **Plan visible en Control Center**: etiqueta coloreada (azul Starter, verde Business, morado Enterprise) en el dashboard por sitio.

### Cambiado

- `sapwc_support_tier` renombrado a `sapwc_plan` (migracion automatica: 'pro' existente -> 'business', nuevas instalaciones -> 'starter').
- Health check devuelve `plan` en lugar de `tier`.
- Site Profile usa `OPTION_PLAN` en lugar de `OPTION_SUPPORT_TIER`.

### Enforcement por plan

- **B2B mode** (Business+): selector deshabilitado en UI + sanitize_callback bloquea guardar 'b2b' sin plan.
- **Catalog import** (Business+): menu oculto + callbacks de cron bloqueados.
- **Multi-warehouse** (Business+): checkboxes extra deshabilitados + sanitize limita a 1 almacen.
- **Multichannel** (Business+): TikTok, Amazon, eBay no se auto-registran sin plan.
- **Miravia** (Enterprise): `register()` bloqueado en channel-manager.
- **Extension hooks** (Enterprise): `sapwc_loaded` y `sapwc_admin_menu` gated.

---
## [2.14.0] - 2026-03-31

### Añadido

- **Campos UDF de pedidos 100% configurables**: todos los User Defined Fields que se envían en el payload de pedidos a SAP son ahora configurables desde la página de Ajustes de Sincronización. Se eliminan 45+ referencias hardcodeadas a campos específicos de un partner (U_ARTES_*, U_DRA_*, U_DNI).
- **Sección «Campos UDF de Pedidos» en admin**: nueva tabla en Ajustes de Sincronización donde el usuario mapea cada función (portes, ruta, nombre comercial, teléfono, DNI, alerta, observaciones agencia, observaciones pedido, comentarios almacén, cantidad regalo) al nombre UDF de su instancia SAP, con valor por defecto configurable.
- **Funciones helper `sapwc_inject_udf()`, `sapwc_udf_field()`, `sapwc_udf_value()`**: API interna para inyectar UDFs al payload condicionalmente según la configuración.
- **Migración automática**: al actualizar, las instalaciones existentes reciben los valores legados pre-populados (sin pérdida de funcionalidad). Las nuevas instalaciones arrancan con campos vacíos.
- **Defaults de extensiones persistidos**: la migración guarda explícitamente los defaults de extensiones para que no dependan de valores hardcodeados en código.

### Cambiado

- El default del campo UDF de cliente web (`sapwc_customer_udf_field`) pasa de `U_ARTES_CLIW` a vacío. Las instalaciones existentes ya tienen el valor almacenado; las nuevas deben configurarlo.
- Los defaults de la página de Extensiones pasan de valores ARTES a vacíos (las instalaciones existentes conservan sus valores gracias a la migración).

### Eliminado

- 45+ literales hardcodeados de `U_ARTES_*`, `U_DRA_*` y `U_DNI` en `class-sap-sync.php`, `class-channel-detector.php`, `class-extensions-page.php`, `sap-woo-suite.php`, `class-sync-options-page.php`, `class-customers-import-page.php`, `class-customer-sync.php` y `class-selective-import-page.php`.

---
## [2.13.0] - 2026-03-30

### Añadido

- **Autoloader por class-map**: sustituye 29 `require_once` manuales por `spl_autoload_register` con mapa de clases. Las clases sin side-effects se cargan bajo demanda (lazy-load). Los ficheros con hooks a nivel de archivo (ajax, cron, columns) se cargan explícitamente via `class_exists()`.
- **`wp_cache` para field mappings**: `SAPWC_Sync_Handler::get_cached_mapping()` centraliza la lectura de `sapwc_field_mapping` con capa `wp_cache_get/set` (grupo `sapwc`). Beneficia sitios con object cache persistente (Redis/Memcached). Se invalida automáticamente al guardar el mapeo.

### Eliminado

- 29 líneas de `require_once` en `sapwc_load_dependencies()` → 2 `require_once` (autoloader + helper.php) + 5 `class_exists()`.
- `require_once` redundantes de `helper.php` en `class-sap-sync.php`, `class-orders-page.php`, `class-sap-orders-table.php` y `class-customers-import-page.php`.
- Fallbacks de `SAPWC_PLUGIN_PATH` en ficheros individuales (ya innecesarios con el autoloader).

---
## [2.12.1] - 2026-03-30

### Corregido

- **Ajuste de redondeo fallaba en servidores SAP que no soportan $expand**: el GET `/Orders(X)?$expand=DocumentLines` devuelve HTTP 400 en algunas versiones de SAP B1 Service Layer. Ahora hace fallback automatico: GET del pedido + GET de `/Orders(X)/DocumentLines` por separado. Tambien corrige la extraccion de lineas cuando SAP las devuelve bajo la key `DocumentLines` en vez de `value`.
- **Log de error de ajuste mejorado**: ahora incluye el detalle real del error SAP en vez de un mensaje generico.

---
## [2.12.0] - 2025-07-08

### Anadido

- **Site Profile y Health Check**: cada instalacion genera un `site_id` unico y expone `/wp-json/sapwc/v1/health` (autenticado por webhook secret) para monitoreo remoto.
- **Feature Flags con kill-switch**: sistema de flags remotos servidos desde GitHub Pages con cache de 12 h. Permite deshabilitar crons, endpoints y features por sitio o globalmente sin deploy.
- **API Secret auto-generado**: `sapwc_webhook_secret` se genera automaticamente en activacion y en `plugins_loaded` (para installs existentes que actualicen sin reactivar).
- **Seccion "Conexion Control Center"** en el dashboard multicanal: muestra Site ID, API Secret y Health URL siempre visible (no requiere WP_DEBUG).
- **Config Export/Import**: exporta e importa la configuracion completa del plugin en JSON desde el dashboard.

### Corregido

- **Version mismatch**: header del plugin decia 2.11.11 pero la constante SAPWC_VERSION era 2.11.6. Sincronizado.

---
## [2.11.6] - 2026-03-18

### Corregido

- **Error 500 (timeout) en exportacion manual a SAP**: `wc_get_orders(['limit' => -1])` cargaba todos los pedidos en una sola request AJAX, lo que provocaba que el servidor superara el tiempo limite (30 s) cuando habia muchos pedidos pendientes. Solucion: la sincronizacion ahora se procesa en lotes de 30 pedidos mediante requests AJAX secuenciales. Cada request devuelve `has_more` y `next_offset`; el JS lanza el siguiente lote hasta completar todos los pedidos.
- **Fatal PHP en actualizacion del plugin (`TypeError: rtrim() — WP_Error`)**: el filtro `upgrader_source_selection` llamaba a `untrailingslashit($source)` cuando `$source` era un `WP_Error` (fallo de descarga), produciendo un error fatal. La guarda `is_wp_error()` ya existia en el codigo local pero no habia sido desplegada.

### Mejorado

- **Control de timeout por lote**: se anade `@set_time_limit(120)` e `ignore_user_abort(true)` al handler AJAX de sincronizacion para garantizar que cada lote tenga margen suficiente independientemente del `max_execution_time` del servidor.

---
## [2.11.5] - 2026-03-16

### Añadido

- **Exclusión manual permanente de pedidos (`_sap_no_sync`)**: un pedido con la meta `_sap_no_sync=1` queda bloqueado en `send_order()` y en el resync masivo, sin importar su estado ni si el documento SAP fue cancelado. Permite a los gestores de SAP B1 excluir pedidos concretos sin modificar código. Se activa/desactiva desde el debug script con `?action=exclude_order&order_id=X` / `?action=include_order&order_id=X`.

---
## [2.11.4] - 2026-03-16

### Corregido

- **Auto-rescue de crons de stock y limpieza**: si `sapwc_cron_sync_stock` o `sapwc_log_cleanup_cron` llevan más de 10 min / 1 hora vencidos respectivamente, se reprograman automáticamente en el siguiente `admin_init`. Completa la cobertura del sistema de auto-recovery introducido en v2.11.2 (que solo rescataba el cron de pedidos).

---
## [2.11.3] - 2026-03-16

### Corregido

- **`send_order()` fallaba con reembolsos (`WC_Order_Refund`)**: al ejecutar una sincronización masiva de pedidos con estado `completed`, WooCommerce puede incluir objetos `WC_Order_Refund` en los resultados. `send_order()` llamaba a `get_order_number()` que no existe en esa clase, produciendo un `Fatal Error` que abortaba toda la sincronización. Fix: guard al inicio de `send_order()` — si el objeto es de tipo `shop_order_refund`, retorna `skipped` inmediatamente.

---
## [2.11.2] - 2026-03-10

### Corregido

- **B2B modo sin_cargo — cantidad incorrecta (12 uds)**: `unidades_caja` estaba en la cadena de fallback de `pack_size` en `build_items_sin_cargo()`, haciendo que cualquier pedido con menos unidades que `unidades_caja` (ej. 12) se forzara a ese mínimo. `unidades_caja` es info de embalaje, no cantidad mínima de pedido; eliminado de la cadena. Solo se respetan `compra_minima`, `_klb_min_quantity` y `_klb_step_quantity`.
- **`$item->set_quantity()` mutaba el pedido WooCommerce**: al ajustar la cantidad al mínimo de compra en `build_items_sin_cargo()`, se llamaba `$item->set_quantity($quantity)` sobre el ítem real del pedido. Cuando `send_order()` completaba el envío a SAP y llamaba `$order->save()`, esa cantidad se persistía en WooCommerce. Resultado: el pedido en WooCommerce quedaba con las mismas unidades incorrectas que SAP. Fix: eliminado `set_quantity()`; el ajuste de cantidad ahora solo modifica las variables locales del payload y nunca toca los ítems del pedido., se llamaba `$item->set_quantity($quantity)` sobre el ítem real del pedido. Cuando `send_order()` completaba el envío a SAP y llamaba `$order->save()`, esa cantidad se persistía en WooCommerce. Resultado: el pedido en WooCommerce quedaba con las mismas unidades incorrectas que SAP. Fix: eliminado `set_quantity()`; el ajuste de cantidad ahora solo modifica las variables locales del payload y nunca toca los ítems del pedido.

- **`check_order_in_sap` devolvía documentos cancelados**: La consulta OData `/Orders?$filter=NumAtCard eq '...'` retornaba también documentos ya cancelados en SAP. Si se cancelaba un documento y se limpiaba `_sap_exported`, `send_order()` encontraba el doc cancelado, lo marcaba como exportado de nuevo (con el DocEntry cancelado) y saltaba el envío. Fix: añadido `and Cancelled eq 'tNO'` al filtro para ignorar documentos cancelados.

### Añadido

- **Cron auto-recovery**: Hook `admin_init` detecta si `sapwc_cron_sync_orders` lleva más de 5 min vencido (WP-Cron no dispara por loopback bloqueado en entornos locales) y ejecuta la sincronización directamente protegida por `sapwc_cron_orders_lock`. Reprograma el cron desde ese momento para que los próximos disparos sean predecibles. Solo activo si `sapwc_sync_orders_auto = 1` y el usuario tiene permiso `edit_others_shop_orders`.

---
## [2.10.0] - 2026-03-05

### Agregado — Design System + Dashboard + B2B Tarifa Individual

- **Design System CSS**: Sistema de diseno completo con CSS custom properties — tokens de color, tipografia, espaciado, sombras, bordes. Sin framework externo, zero bloat
- **Dashboard con graficos**: Revenue trend (linea), Orders trend (barras), Sync rate (donut), Channel distribution (donut). Chart.js 4.4.7 via CDN, cargado condicionalmente solo en dashboard
- **Panel B2B**: Top clientes por revenue, distribucion de tarifas individuales vs global, resumen con barra de progreso de clientes vinculados
- **Panel B2C**: Top productos por cantidad, resumen de canales y estado de sync
- **KPI cards**: 4 tarjetas estadisticas (pedidos 30d, revenue 30d, tasa de sync, clientes/canales) con iconos y acentos de color
- **Tarifa Individual B2B**: Toggle `sapwc_b2b_individual_tariff` (OFF por defecto). Cuando activo, `PriceListNum` del BP en SAP se aplica a cada linea del pedido en lugar de la tarifa global
  - `class-customer-sync.php`: Importa `PriceListNum` y lo guarda en `sapwc_price_list` user meta
  - `class-sap-sync.php`: `build_items()` acepta `$customer_tariff_override` para aplicar tarifa del BP
  - `class-customers-import-page.php`: Columna "Tarifa SAP" con indicadores visuales
  - `class-sync-options-page.php`: Seccion toggle B2B con explicacion

### Mejorado

- **UI admin completa**: Todas las paginas admin (12) usan clase `sapwc-wrap` para estilos consistentes
- **Tablas**: Bordes redondeados, headers uppercase, hover rows, paginacion DataTables estilizada
- **Botones**: Primario con brand color, secundario ghost, estados disabled
- **Badges**: Pill badges para success/warning/danger/info/neutral
- **Toggle switch**: Rediseñado 44x24px con animacion suave
- **Formularios**: Focus rings con brand color, inputs con radius y transiciones
- **`sapwc-toggle.css` eliminado**: Mergeado en `sapwc-admin.css`
- **ARCHITECTURE.md**: Actualizado con nueva estructura de assets

### Lite v1.1.0

- Mismo design system CSS aplicado al plugin Lite
- Tabs, inputs, botones, badges y tablas con estilos unificados
- PRO Features page: inline styles movidos a CSS externo
- Version badge rediseñado

---
## [2.9.1] - 2026-03-04

### Corregido — Importador de clientes UDF (SEGURIDAD) + log spam

- **SEGURIDAD — Importador de clientes**: Clientes sin el UDF de cliente web (`U_ARTES_CLIW = S`, configurable) ya no se importan en ningún flujo. Triple capa: validación en AJAX individual (incluye campo UDF en `$select` y rechaza si no coincide), filtro PHP en lote cuando SAP no soporta el UDF en OData, y guardia de último recurso en `SAPWC_Customer_Sync::import_customer()`
- **Spam de logs — Channel Manager**: Eliminado el `SAPWC_Logger::log()` de `register()`, que se llamaba en cada petición WordPress generando cientos de entradas "Canal registrado correctamente" cada minuto

### Agregado

- **Vista previa de clientes pendientes**: Panel estadístico en la página de importación (Total SAP / Ya importados / Pendientes) con tabla de los próximos 15 clientes, botón Actualizar y recarga automática tras importación en lote

### Lite v1.0.2

- URLs corregidas: `replanta.dev` → `replanta.net/conector-sap-woocommerce/`
- URL de soporte → `replanta.net/contacto/`

---
## [2.9.0] - 2026-03-04

### Corregido — Parse error crítico en v2.6.3

- Eliminado bloque `return $source; }, 10, 4);` duplicado en `sap-woo-suite.php` (línea 199) que causaba `PHP Parse error: Unmatched '}'` al activar el plugin

### Mejorado — Validación pre-deploy

- `build.ps1`: añadido paso **PHP lint** (`php -l`) sobre todos los archivos `.php` antes de construir el ZIP — el build aborta si hay errores de sintaxis
- `.git-hooks/pre-commit`: hook git que ejecuta `php -l` sobre los archivos `.php` en stage antes de cada commit
- `.vscode/tasks.json`: tareas **PHP Lint**, **Build ZIP** y **Deploy** accesibles desde `Terminal → Run Task`
- `build.ps1`: auto-configura `git config core.hooksPath .git-hooks` en cada ejecución

---
## [2.6.3] - 2026-03-03

### Corregido — Mecanismo de actualización automática (PUC)

- **Bug crítico**: `setBranch('main')` reemplazado por `enableReleaseAssets()` — PUC ahora descarga el ZIP del asset de cada GitHub Release en lugar del ZIP automático de rama, cuya carpeta raíz (`replantadev-sap-woo-suite-HASH-main/`) causaba la creación de un plugin duplicado
- **Bug**: Lógica del filtro `upgrader_source_selection` corregida — la condición `strpos(...) === false` impedía renombrar cuando la carpeta YA contenía 'sap-woo-suite' (que es exactamente el caso del ZIP de rama); ahora compara rutas completas con `untrailingslashit`
- **build.ps1**: El paso `-Deploy` ahora crea automáticamente un GitHub Release con el ZIP controlado como asset, requisito previo para que `enableReleaseAssets()` funcione

---## [2.9.0] - 2026-03-04

### Agregado
- **Retry automático 401**: Reconexión automática cuando expira la sesión SAP
- **REST API pública**: Endpoints `/sync-order` y `/sync-products` para integraciones externas
- **Tests expandidos**: 36 tests unitarios (API Client + REST API)
- **Documentación Docsify**: Sistema de docs con SEO integrado

### Mejorado
- Refactor de `API_Client::get()` para delegar en `request()`
- Escape seguro de queries OData con `sapwc_escape_odata()`

---## [2.6.5] - 2026-03-02

### Mejorado — Reorganización del menú de administración

- **Pedidos Fallidos** movido junto a **Pedidos** (misma entidad, distinto estado)
- **"Pedidos Woo"** renombrado a **"Pedidos"** ("Woo" era redundante en contexto WooCommerce)
- Configuración (Sincronización, Mapeo, Conexión SAP, Registros) agrupada al final, separada de las operaciones diarias
- **"Credenciales SAP"** renombrado a **"Conexión SAP"** (más amigable)
- **"Logs"** renombrado a **"Registros"** (español profesional)
- Canales externos (TikTok, Miravia…) ahora aparecen entre operaciones y configuración — ya no cuelgan ocultos bajo "Conexión SAP"
- Orden final: Escritorio → Pedidos → Pedidos Fallidos → Importación → ↳ Canales → Sincronización → Mapeo → Conexión SAP → Registros

---
## [2.6.2] - 2026-03-02

### Mejorado — Calidad de código (SonarQube)

- Añadidas constantes `SAPWC_ERR_NO_PERM`, `SAPWC_ERR_NO_CONN`, `SAPWC_ERR_LOGIN`, `SAPWC_ERR_UNKNOWN`, `SAPWC_DATETIME_FMT` para eliminar literales duplicados
- Parámetro no usado `$upgrader` renombrado a `$_upgrader` en el filtro `upgrader_source_selection`
- `if` anidado en `upgrader_source_selection` fusionado en una sola condición
- Eliminados trailing whitespaces en ternarios multilínea

---
## [2.6.6] - 2026-03-02

### Corregido — Fatal error en actualización automática

- `upgrader_source_selection`: añadido guard `is_wp_error($source)` al inicio del filtro — cuando un paso previo del upgrader falla y devuelve `WP_Error`, el filtro lo devolvía a `untrailingslashit()` que llama a `rtrim()` con tipo incorrecto → `PHP Fatal error: TypeError: rtrim(): Argument #1 must be of type string, WP_Error given`

---
## [2.6.1] - 2026-03-02

### Mejorado — Rediseño de la página de Sincronización

- **Layout 3 cards iguales**: Pedidos | Stock y Precios | Auto-Sync Catálogo se muestran siempre como tres columnas equilibradas
- **Sección Ecommerce independiente**: CardCodes (Península/Canarias/Portugal) + Nombre sitio en columna izquierda; Tarifas + IVA + Portes en columna derecha — solo visible en modo Ecommerce, full-width debajo de los 3 cards
- Eliminado el hueco vacío que aparecía al cambiar entre modos Ecommerce y B2B

---

## [2.6.0] - 2026-03-02

### Añadido — Motor de mapeo de campos funcional + UI rediseñada (Paso B)

#### Motor (`includes/class-product-sync.php`)
- Nuevo método privado `apply_field_mapping()` — lee la opción `sapwc_field_mapping` y aplica cada regla al producto WC antes de guardarlo
  - Se ejecuta **después** de todos los campos por defecto; si el mismo destino aparece en ambos, el mapper tiene prioridad
  - Formato de la opción: array de objetos `[{source, destination}]` (migra automáticamente desde el formato legacy plano)
  - Destinos soportados: `post_title`, `post_content`, `post_excerpt`, `_weight`, `_global_unique_id`, `meta:<clave>`, `wc_attribute:<nombre>`
  - Arrays y objetos SAP (ItemPrices, etc.) se omiten para evitar errores
- Nuevo método privado `set_product_attribute()` — crea/actualiza atributos locales WC (no taxonomía) en el producto

#### UI (`admin/class-mapping-page.php`) — reescritura completa
- **Sección 1** — Tabla de campos por defecto (siempre activos, solo lectura) incluyendo los 4 clásicos + los 3 nuevos de v2.5
- **Sección 2** — Mapeo adicional dinámico:
  - Filas añadibles/eliminables con botón
  - Dropdown de campo SAP origen: campos estándar + todos los UDFs de SAP (`UserFieldsMD?$filter=TableName eq 'OITM'`), cargados dinámicamente vía AJAX al abrir la página
  - Dropdown de destino WC con grupos: *Campos nativos*, *Atributo WC* (con input de nombre), *Meta personalizado* (con input de clave)
  - Guardado vía AJAX sin recarga de página
- **Sección 3** — Vista previa de mapeo con producto real:
  - Input de SKU (ItemCode SAP) + botón Cargar
  - Consulta el item a SAP y el producto WC en paralelo
  - Tabla lado a lado: campo SAP | valor en SAP | destino WC | valor actual en WC | estado (sin cambio / se actualizará)
  - Filas de defaults resaltadas en azul claro; filas personalizadas en blanco
  - Enlace directo al producto WC (si existe)
- Sección 4 (envío masivo) y Sección 5 (inspector metadata) conservadas con nonces CSRF corregidos
- Añadidos AJAX handlers: `sapwc_get_sap_fields`, `sapwc_preview_mapping` (reemplaza handlers anteriores)
- Handler `sapwc_save_mapping` actualizado para el nuevo formato de datos

#### Assets (`assets/js/mapping.js`) — nuevo archivo
- Carga dinámica de campos SAP al cargar la página
- Manejo completo de filas dinámicas (añadir/eliminar)
- Toggle automático del input de clave para destinos `wc_attribute:` y `meta:`
- Guardado AJAX con feedback visual
- Renderizado completo de tabla de vista previa con código de color por estado

---

## [2.5.0] - 2026-03-02

### Añadido — Sincronización de ficha completa de producto (Paso A)
- `User_Text` de SAP → `post_content` (descripción larga): ingredientes, modo de uso, información nutricional
  - Se sanea con `wp_kses_post` + `nl2br` para respetar saltos de línea del texto SAP
  - Se sincroniza siempre (SAP es fuente autoritativa del campo)
- `SalesUnitWeight` de SAP → `_weight` de WooCommerce (en la unidad configurada en Ajustes > Medidas)
  - Solo se aplica si el valor es mayor que 0
- `BarCode` de SAP → `_global_unique_id` (GTIN nativo WC 8.4+) además del meta `_sapwc_barcode` existente
- Campos `User_Text` y `SalesUnitWeight` añadidos al `$select` de `get_select_fields()` — se obtienen en el mismo request, sin coste adicional de API

---

## [2.4.0] - 2026-03-02

### Añadido — Action Scheduler para reintentos automáticos
- Nueva clase `SAPWC_Retry_Scheduler` — cuando `send_order()` falla, programa reintentos automáticos con back-off exponencial: +1 min, +5 min, +30 min (máx. 3 intentos)
- Usa Action Scheduler (incluido con WooCommerce) en lugar de WP-Cron manual
- Máximo de reintentos configurable vía constante `MAX_ATTEMPTS`; pedidos agotados reciben meta `_sap_retry_exhausted = 1` para visibilidad en panel
- En desactivación del plugin se cancelan todos los jobs pendientes (`as_unschedule_all_actions`)

### Añadido — Idempotency key / mutex de envío
- Transient `sapwc_sending_{order_id}` (TTL 90 s) puesto ANTES del `POST /Orders` y eliminado inmediatamente al recibir respuesta
- Si dos instancias de WP-Cron se solapan para el mismo pedido, la segunda detecta el mutex activo y aborta sin duplicar el pedido en SAP
- Log de tipo `warning` cuando se descarta un envío por mutex activo

### Añadido — Índices DB en `wp_sapwc_logs`
- `INDEX idx_order_id (order_id)` — elimina full-table-scan en consultas por pedido en el Log Viewer y en la página de Pedidos Fallidos
- `INDEX idx_status_created (status, created_at)` — cobertura para `WHERE status = 'error' ORDER BY created_at DESC` y para el cron DELETE de limpieza
- `sapwc_create_log_table()` actualizada para incluir los índices en instalaciones nuevas
- `sapwc_migrate_log_indices()` crea los índices automáticamente en instalaciones existentes (hook `plugins_loaded`, se ejecuta una sola vez vía flag `sapwc_log_indices_v240`)

### Añadido — Webhook receiver de stock SAP
- Endpoint REST `POST /wp-json/sapwc/v1/stock-update` — SAP B1 (o cualquier sistema externo) puede empujar cambios de stock en tiempo real sin polling
- Autenticación por cabecera `X-SAPWC-Secret` comparada con `hash_equals()` (timing-safe)
- Acepta array JSON `[{"ItemCode":"SKU","OnHand":42}, ...]` o payload de objeto único
- Actualiza `stock_quantity` y `stock_status` en WooCommerce; reactiva gestión de stock si estaba desactivada en el producto
- Activa gestión de stock en el producto si no lo tenía habilitado
- Responde con `{"updated":N,"skipped":N,"errors":[]}` y código 207 en fallos parciales
- Documentación de integración SAP B1 incluida en cabecera del archivo

### Añadido — Suite de tests PHPUnit
- `phpunit.xml` con configuración para PHPUnit 9.x, coverage de `includes/`
- `tests/bootstrap.php` — carga Composer autoloader + stubs de funciones WP sin entorno WP completo
- `tests/class-test-logger.php` — 4 tests: insert correcto, coerción de order_id no numérico, docentry como int, fallo de DB silencioso
- `tests/class-test-rounding.php` — 4 tests: sin ajuste cuando totales cuadran, ajuste positivo, respuesta SAP vacía, ajuste negativo
- `composer.json` — añadido `require-dev`: `phpunit/phpunit: ^9.6`, `brain/monkey: ^2.6`, `mockery/mockery: ^1.6`
- Ejecutar con: `composer install --dev && vendor/bin/phpunit`

---

## [2.3.0] - 2026-03-02

### Refactorizado — HTTP Client unificado
- Todas las llamadas directas `wp_remote_get/post/request` en `SAPWC_Sync_Handler` migradas a `$this->client->get/post/patch()` — 6 puntos migrados
- Eliminada dependencia de configuración manual de headers SAP en cada llamada; el cliente gestiona sesión, timeout y reintentos

### Corregido — `add_rounding_adjustment_if_needed()`
- Reactivada la función de ajuste de redondeo (estaba deshabilitada desde v2.1.0)
- Bug: usaba `GET` por `NumAtCard` en lugar de `DocEntry`; corregido a `GET /Orders($docentry)?$expand=DocumentLines`
- Bug: array de provincias con IVA incluía `PM` (Baleares, con IVA) y `LP` (inválida); reemplazado por `should_include_vat_for_region()`
- Solo se ejecuta en modo ecommerce (B2B los totales se gestionan aparte)

### Añadido — Lock anti-race en Miravia
- Transient de 60s `sapwc_miravia_importing_{id}` antes de `wc_create_order()` en el addon Miravia
- Evita duplicados cuando dos instancias de WP-Cron solapan durante el import

### Corregido — Sistema de logs (5 bugs)
- `$wpdb->insert` sin array de formatos: añadido `['%d','%s','%s','%s','%d','%s']`
- Zona horaria: `wp_date()` sustituido por `current_time('mysql')` para coherencia con `NOW()` en DELETE del cron
- XSS: `esc_html()` en las 6 columnas del log viewer (`created_at`, `order_id`, `action`, `status`, `message`, `docentry`)
- CSRF: eliminado trigger `$_GET['sapwc_test_log']` sin nonce (el handler AJAX con nonce ya existía)
- Cron de limpieza: añadida verificación de existencia de tabla vía `information_schema` antes del DELETE

---

## [2.1.0] - 2025-06-26

### Añadido — Detección Automática de Canales
- **`SAPWC_Channel_Detector`**: Nueva clase que detecta automáticamente el canal de origen de cada pedido por metadata
- Detectores integrados para **TikTok Shop** (`tiktok_order`), **Amazon** (`_amazon_order_id`), **eBay** (`_ebay_order_id`)
- Meta `_sapwc_channel` se guarda en cada pedido para tracking permanente
- Filtro `sapwc_channel_detectors` para añadir detectores personalizados de terceros
- Filtro `sapwc_channel_payload` para modificar el payload después de inyectar canal

### Añadido — Inyección de Canal en SAP
- El canal de origen se inyecta automáticamente en `Comments` del pedido SAP (ej: `[TIKTOK SHOP] NAD+ | #123...`)
- Metadata extra del marketplace (TikTok Order ID, etc.) se añade a `U_ARTES_Observ`
- Soporte para UDF personalizado en SAP (ej: `U_Canal`) configurable desde el dashboard

### Añadido — Dashboard Multicanal Mejorado
- **Estadísticas por canal**: pedidos, facturación y % sincronizado con SAP (30 días + histórico)
- Tarjetas visuales con datos por canal (colores, iconos, contadores)
- Tabla histórica con totales acumulados por canal
- Tabla de detectores configurados con estado del plugin asociado
- Herramienta "Re-etiquetar" para asignar canal a pedidos existentes
- Configuración de campo UDF para SAP directamente desde el dashboard

### Añadido — Registro Automático de Canales
- `SAPWC_Channel_Manager::register_builtin_channels()` registra canales automáticamente según plugins activos
- WooCommerce siempre registrado como canal nativo
- TikTok Shop se registra si `tiktok-for-woocommerce` está activo
- Amazon se registra si detecta plugins conocidos (WP-Lister, Amazon for WooCommerce)
- eBay se registra si detecta WP-Lister for eBay
- Hook `sapwc_builtin_channels_registered` para extender

### Añadido — Columna "Canal" en Pedidos WooCommerce
- Columna visual en la tabla de pedidos mostrando el canal de origen
- Compatible con HPOS y modo legacy (CPT)

### Cambiado
- **Filosofía**: De addons custom por marketplace → plugins oficiales + detección automática
- Dashboard ya no muestra WooCommerce como bloque separado estático, sino como canal registrado más

### Compatibilidad
- Funciona con el plugin oficial **TikTok for WooCommerce** v1.3.x
- Funciona con cualquier plugin que escriba meta en pedidos WooCommerce
- No requiere el addon `sap-woo-suite-tiktok` (puede desactivarse)

---

## [2.0.0] - 2025-06-25

### Añadido — Arquitectura Multichannel
- **Sistema de Canales (Addons)**: `SAPWC_Channel_Manager` para registrar y gestionar canales/marketplace
- **Interface + Base Abstracta**: `SAPWC_Channel_Interface` y `SAPWC_Channel_Base` para crear addons
- **Dashboard Multicanal**: Nueva página principal mostrando estado de SAP, canales registrados y estadísticas
- **Hook `sapwc_loaded`**: Punto de enganche principal para que addons se registren al cargar el plugin
- **Hook `sapwc_admin_menu`**: Para que addons añadan submenús al menú de SAP Woo
- **Hook `sapwc_channel_registered`**: Se dispara cuando un canal se registra

### Añadido — Hooks de extensión
- `apply_filters('sapwc_order_payload', $payload, $order)` — Modificar payload antes de enviar a SAP
- `do_action('sapwc_before_send_order', $order, $payload)` — Antes de enviar pedido
- `do_action('sapwc_after_send_order', $order, $body, $success, $sap_id)` — Después de enviar (éxito o error)
- `apply_filters('sapwc_before_save_product', $product, $item_data, $is_new)` — Modificar producto antes de guardar
- `do_action('sapwc_after_import_product', $saved_id, $item_data, $is_new)` — Después de importar producto
- `do_action('sapwc_after_import_customer', $user_id, $customer_data)` — Después de importar cliente
- `apply_filters('sapwc_documents_owner', $owner, $order)` — DocumentsOwner configurable por pedido
- `apply_filters('sapwc_api_request_args', $args, $method, $endpoint)` — Modificar args de API
- `apply_filters('sapwc_api_login_args', $credentials)` — Modificar credenciales de login

### Añadido — API Client mejorado
- **Patrón Singleton**: Reutiliza sesión SAP B1SESSION en lugar de abrir una nueva por operación
- Métodos `post()`, `patch()`, `delete()` además del existente `get()`
- Método genérico `request()` para cualquier verbo HTTP
- `is_logged_in()`, `is_ssl()`, `get_base_url()`, `get_cookie_header()` públicos
- `reset_instances()` para testing

### Corregido — Bugs críticos
- **DocumentsOwner hardcoded**: Ya no se fuerza a `97`, ahora usa `sapwc_user_sign` de la configuración
- **HPOS Compatible**: Pedidos usan `$order->update_meta_data()` + `$order->save()` en lugar de `update_post_meta()`
- **Nonce en Extensions**: La página de extensiones ahora verifica nonce CSRF antes de procesar POST
- **SSL en API Client**: Usa `$this->client->is_ssl()` correctamente en lugar de `$this->conn['ssl']` que no existía

### Cambiado
- Plugin renombrado de `sap-wc-sync` a `sap-woo-suite`
- Declaración de compatibilidad HPOS via `FeaturesUtil::declare_compatibility()`
- Orden de carga reestructurado: Core → Channel Manager → Business Logic → Admin
- Constantes añadidas: `SAPWC_VERSION`, `SAPWC_PLUGIN_FILE`, `SAPWC_PLUGIN_BASENAME`
- Menú admin reorganizado con Dashboard como primera opción
- GitHub Updater apuntando a repositorio `sap-woo-suite`

---

## [1.4.2-beta] - 2025-02-26

### Añadido
- **Nueva página de Importación Selectiva** (SAP Woo > Importar Selectivo)
- Visualización de productos, categorías y clientes de SAP que NO están en WooCommerce
- Modal de **Vista Previa de Campos** antes de importar:
  - Muestra campos origen de SAP
  - Muestra mapeo hacia campos WooCommerce
  - Indica si el elemento ya existe o se creará nuevo
- Importación individual asíncrona con feedback visual
- Selección múltiple para importar varios elementos a la vez
- Barra de progreso para importaciones bulk
- Métodos de preview en clases de sincronización:
  - `SAPWC_Product_Sync::get_product_preview()`
  - `SAPWC_Product_Sync::get_pending_products()`
  - `SAPWC_Category_Sync::get_category_preview()`
  - `SAPWC_Category_Sync::get_pending_categories()`
  - `SAPWC_Customer_Sync::get_customer_preview()`

### Mejorado
- UI profesional con modal, tablas y feedback visual
- Código de sincronización más modular y reutilizable

---

## [1.4.1-beta] - 2025-02-26

### Seguridad
- Eliminado `print_r($connection)` que exponía credenciales en logs
- Eliminado código de testing hardcoded ("Sandra González")
- Eliminadas líneas de debug visibles en el panel de administración
- Todos los `error_log` sensibles ahora requieren `WP_DEBUG` activo

### Eliminado
- Emojis restantes reemplazados por dashicons (\u23f0, \ud83d\udc47)
- Emojis en JavaScript (\u274c, \u2705, \u231b, \ud83d\udee9)
- Logs verbose de BUILD_ITEMS que escribían por cada producto
- Logs de debug regional que escribían por cada pedido

### Corregido
- `DocumentsOwner` ahora usa `sapwc_user_sign` configurable en lugar de valor hardcoded
- Tipo de log cambiado de 'error' a 'warning' para ajustes de cantidad mínima

---

## [1.4.0-beta] - 2026-02-25

### Añadido
- Nueva página de **Importación Unificada** con 3 pestañas (Productos, Categorías, Clientes)
- Importación por lotes con barra de progreso visual
- Guía de operario integrada en la interfaz de importación
- Descripciones contextuales en cada sección
- Estadísticas de importación en tiempo real

### Cambiado
- Todos los emojis reemplazados por iconos nativos de WordPress (dashicons)
- Interfaz más profesional y consistente
- Mejoras en los mensajes de error y logs

### Corregido
- Bug de duplicación de extraData en el importador por lotes
- Múltiples correcciones menores de UI

---

## [1.3.0-beta] - 2025-12-15

### Añadido
- **Sincronización automática de clientes SAP → WooCommerce** (modo B2B)
- Campo UDF configurable para marcar clientes como "cliente web"
- Email de bienvenida personalizado con logo y colores del sitio
- Enlace para establecer contraseña en el email de bienvenida
- Cron diario configurable para sincronización de clientes
- Herramientas de debug en consola para verificar mapeo de campos

### Cambiado
- Mejoras en la gestión de clientes B2B
- Optimización del proceso de importación

---

## [1.2.79] - 2025-10-01

### Corregido
- Mejoras de estabilidad general
- Correcciones de bugs menores en sincronización de pedidos

---

## [1.2.78] - 2025-09-15

### Añadido
- Soporte para tarifas regionales independientes (Península, Canarias, Portugal)
- Mapeo de almacén-tarifa personalizable
- Configuración de IVA por región

### Cambiado
- Refactorización del sistema de tarifas

---

## [1.2.70] - 2025-08-01

### Añadido
- Sincronización de gastos de envío (DocumentAdditionalExpenses)
- Código de gasto y TaxCode configurables para portes
- Toggle para activar/desactivar sincronización de portes

---

## [1.2.60] - 2025-07-01

### Añadido
- Modo de descuento "Sin Cargo" para promociones con unidades gratuitas
- Campo U_ARTES_CantSC para unidades sin cargo

### Cambiado
- Mejoras en el cálculo de descuentos por línea

---

## [1.2.50] - 2025-06-01

### Añadido
- Toggles independientes para sincronización automática de stock y precios
- Intervalo configurable para sincronización automática
- Mejor gestión de cron jobs

---

## [1.2.40] - 2025-05-01

### Añadido
- Tabla de pedidos SAP con vista directa desde Service Layer
- DataTables para mejor experiencia de usuario
- Indicador de estado de conexión en tiempo real

---

## [1.2.30] - 2025-04-01

### Añadido
- Sistema de pedidos fallidos con opción de reintento
- Logs detallados por pedido
- Filtrado de pedidos por estado de sincronización

---

## [1.2.20] - 2025-03-01

### Añadido
- Modo B2B completo con CardCode por usuario
- Filtros de cliente configurables (prefix, contains)
- Comercial asignado automáticamente desde SAP

---

## [1.2.10] - 2025-02-01

### Añadido
- Clientes genéricos por región (modo Ecommerce)
- Detección automática de Canarias, Portugal, Península

---

## [1.2.0] - 2025-01-15

### Añadido
- Sincronización bidireccional básica
- Conexión con SAP Service Layer
- Envío de pedidos WooCommerce → SAP
- Importación de stock SAP → WooCommerce

---

## [1.0.0] - 2024-11-01

### Añadido
- Versión inicial del plugin
- Conexión básica con SAP Business One
- Prueba de concepto para sincronización de pedidos

---

[1.4.0-beta]: https://github.com/replantadev/sapwoo/compare/v1.3.0-beta...v1.4.0-beta
[1.3.0-beta]: https://github.com/replantadev/sapwoo/compare/v1.2.79...v1.3.0-beta
[1.2.79]: https://github.com/replantadev/sapwoo/releases/tag/v1.2.79
