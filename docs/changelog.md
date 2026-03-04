# Changelog

Historial de versiones de SAP Woo Suite.

---

## [2.9.0] - 2026-03-04

### Agregado
- **Retry automatico 401**: Reconexion automatica cuando expira la sesion de SAP
- **REST API publica**: Nuevos endpoints `/sync-order` y `/sync-products` para integraciones externas
- **Tests expandidos**: 36 tests unitarios (API Client + REST API)
- **Documentacion Docsify**: Sistema de documentacion con SEO integrado

### Mejorado
- Refactor de `API_Client::get()` para delegar en `request()`
- Escape seguro de queries OData con `sapwc_escape_odata()`

---

## [2.8.5] - 2026-02-15

### Corregido
- Fix paginacion de clientes SAP (respeta `$top` y `$skip`)
- Validacion de UDFs simplificada (fallback silencioso)

### Mejorado
- Landing page SEO optimizada
- Documentacion de instalacion

---

## [2.8.0] - 2026-01-20

### Agregado
- Importador de clientes con paginacion y Action Scheduler
- Soporte para multiples almacenes por tarifa
- Filtro de grupos de articulos en importacion
- Cola de emails de bienvenida para clientes importados

### Corregido
- Encoding de caracteres especiales en OData

---

## [2.7.0] - 2025-12-10

### Agregado
- Dashboard multicanal con vista unificada
- Deteccion automatica de canal (TikTok, Amazon, eBay)
- Field Mapper visual para configurar mapeos
- Soporte para UDFs en productos y pedidos

### Mejorado
- Interfaz de configuracion reorganizada
- Logs mas detallados con rotacion automatica

---

## [2.6.0] - 2025-11-05

### Agregado
- Motor de mapeo de campos SAP a WC con UI dinamica
- Preview de transformaciones en tiempo real
- Validacion de UDFs contra esquema SAP

---

## [2.5.0] - 2025-10-01

### Agregado
- Sincronizacion ficha completa: `User_Text` a descripcion, peso, GTIN
- Importador de productos desde SAP mejorado
- Soporte para productos variables

---

## [2.4.0] - 2025-08-15

### Agregado
- **Action Scheduler**: reintentos automaticos con back-off exponencial
- **Idempotencia**: mutex transient + check `NumAtCard` en SAP
- **Indices DB**: `idx_order_id` + `idx_status_created` en logs
- **Webhook stock**: `POST /wp-json/sapwc/v1/stock-update`
- **PHPUnit**: tests para Logger y Rounding

---

## [2.3.0] - 2025-07-01

### Mejorado
- HTTP Refactor: todas las llamadas via `$this->client`
- Filtros `sapwc_api_request_args` y `sapwc_api_after_request` unificados
- Transient lock en Miravia para evitar imports duplicados

### Corregido
- `add_rounding_adjustment_if_needed()` reparada
- Bugs de logs (XSS, CSRF, timezone)

---

## [2.2.0] - 2025-05-15

### Corregido
- Bug de warehouse: variable usada antes de asignar
- Race condition en meta de pedidos
- Pedidos fallidos con HPOS
- Product sync singleton (evita 25 logins por import)
- Logs sin rotacion

---

## [2.0.0] - 2025-03-01

### Agregado
- Arquitectura modular completa (SAP Woo Suite)
- Sistema de auto-actualizaciones desde GitHub
- Panel de administracion rediseñado
- Soporte multicanal base

### Cambiado
- Requiere PHP 7.4+
- Requiere WooCommerce 6.0+
- Migrado de sap-woo a sap-woo-suite

---

## [1.0.0] - 2024-09-01

### Lanzamiento inicial
- Conexion con SAP B1 Service Layer
- Sincronizacion de pedidos a SAP
- Configuracion basica de credenciales
- Modos B2C y B2B
