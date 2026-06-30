# Portal B2B: Descarga de facturas SAP

> Disponible desde **v2.20.0**. Requiere plan **Business** o **Enterprise** y modo **B2B** activo.

## Visión general

Los clientes B2B con sesión iniciada en la tienda pueden consultar y descargar sus facturas de SAP Business One directamente desde "Mi cuenta" de WooCommerce, sin necesidad de portales externos ni intervención del administrador.

El plugin añade dos puntos de entrada:

1. **Botón "Descargar factura"** en cada fila de la pestaña **Mis pedidos** (cuando el pedido tiene factura asociada en SAP).
2. **Pestaña "Mis facturas"** (`/mi-cuenta/mis-facturas/`) con listado paginado, búsqueda por número y estado de pago.

## Cómo se enlaza pedido <-> factura

El enlace es **automático**: se filtra el endpoint `/Invoices` de SAP por `CardCode = cardcode_del_usuario` y `NumAtCard = numero_del_pedido_woo`.

- `CardCode`: se obtiene del meta de usuario configurado en **SAP Woo Suite > Ajustes > B2B > Campo Meta de Usuario para CardCode** (por defecto `user_login`).
- `NumAtCard`: es el número de pedido de WooCommerce. El plugin lo envía automáticamente al crear el pedido en SAP.

La relación se cachea en el meta del pedido (`_sap_invoice_docentry`, `_sap_invoice_docnum`) para evitar consultas repetidas.

## Fuente del PDF (cadena de fallback)

El plugin intenta obtener el PDF en este orden:

1. **Filtro `sapwc_b2b_invoice_pdf_bytes`** — Para clientes que generan los PDFs en un servicio propio y quieren devolver bytes binarios:

   ```php
   add_filter( 'sapwc_b2b_invoice_pdf_bytes', function ( $bytes, $docentry ) {
       // Devuelve string con el binario del PDF
       return file_get_contents( "/ruta/local/facturas/{$docentry}.pdf" );
   }, 10, 2 );
   ```

2. **Filtro `sapwc_b2b_invoice_pdf_url`** — Para devolver una URL pública (HTTP 30x):

   ```php
   add_filter( 'sapwc_b2b_invoice_pdf_url', function ( $url, $docentry ) {
       return "https://mi-erp.example.com/facturas/{$docentry}.pdf";
   }, 10, 2 );
   ```

3. **Adjunto SAP (`AttachmentsContent`)** — Si la factura tiene un PDF adjunto en SAP (`Invoices(N).AttachmentEntry`), el plugin lo descarga vía Service Layer reutilizando la cookie de sesión.

4. **`ReportLayoutsService_ExportToPdf`** — Como último recurso, exporta el layout (Crystal/PLD) configurado en **SAP Woo Suite > Ajustes > B2B > Portal B2B: Descarga de facturas > LayoutCode**. Si está vacío, SAP usa el layout por defecto del documento.

## Seguridad

- Cada descarga valida:
  - Que el usuario está logueado.
  - Un nonce específico por documento: `sapwc_invoice_pdf_{docentry}`.
  - Que el `CardCode` del usuario coincide con el `CardCode` de la factura en SAP (cross-check vía `Invoices(N)?$select=CardCode`).
- El listado y el PDF se sirven también vía REST con autenticación por cookie de WordPress:
  - `GET /wp-json/sapwc/v1/b2b/my-invoices?page=1&per_page=10&search=...`
  - `GET /wp-json/sapwc/v1/b2b/my-invoices/{docentry}/pdf`

## Configuración rápida

1. **Ajustes > SAP Woo Suite > Sincronización > B2B**:
   - Asegúrate de que el modo está en `B2B`.
   - Confirma que el `Campo Meta de Usuario para CardCode` apunta al campo correcto (por defecto `user_login`).
2. **Portal B2B: Descarga de facturas** (mismo tab):
   - Activa "Activar portal de facturas" (por defecto ya está ON).
   - (Opcional) Indica un `LayoutCode` si quieres forzar un layout concreto para la exportación PDF.
3. Visita `/mi-cuenta/` con un usuario B2B y comprueba que aparece la pestaña **Mis facturas**.

## Permalinks

La pestaña usa un endpoint de WooCommerce (`mis-facturas`). Si tras activar el plugin la URL devuelve 404, ve a **Ajustes > Enlaces permanentes** y guarda los cambios sin modificarlos (esto regenera las reglas de rewrite).

## Personalización visual

- La tabla y el botón se estilizan con `assets/css/sapwc-b2b-invoices.css` (cargado solo en `/mi-cuenta/` y `/mi-cuenta/mis-facturas/`).
- Estados disponibles vía CSS class: `.sapwc-invoice-status--open` (Pendiente), `.sapwc-invoice-status--closed` (Pagada), `.sapwc-invoice-status--cancelled` (Anulada).
- Si quieres un template propio, copia `templates/myaccount-invoices.php` a `wp-content/themes/<tu-tema>/sap-woo-suite/myaccount-invoices.php` (el plugin no lo busca automáticamente; aplica un filtro si lo necesitas).

## Filtros disponibles

| Filtro | Argumentos | Propósito |
|---|---|---|
| `sapwc_b2b_invoice_pdf_bytes` | `$bytes`, `$docentry` | Devolver el PDF como bytes desde fuente externa. |
| `sapwc_b2b_invoice_pdf_url` | `$url`, `$docentry` | Devolver una URL pública para redirección. |
| `sapwc_b2b_invoices_per_page` | `$per_page` | Cambiar tamaño de página (default 10). |
| `sapwc_b2b_invoices_query_args` | `$args`, `$cardcode` | Modificar los `$select`/`$filter` de la consulta a SAP. |

## Limitaciones conocidas

- El matching por `NumAtCard` asume que el número de pedido de Woo es único por cliente en SAP. Si un cliente reenvía el mismo pedido manualmente y crea dos facturas con el mismo `NumAtCard`, se mostrará la primera devuelta por SAP.
- El plugin **no** sincroniza el estado de pago de la factura a WooCommerce; sólo lo muestra al cliente. Para reconciliar pagos contra Woo, usa el módulo de sincronización de cobros (próximamente).
