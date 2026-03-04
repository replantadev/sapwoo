# Changelog

Historial de versiones de SAP Woo Suite.

---

## [2.9.0] - 2025-01-XX

### Añadido
- **Retry automático 401**: Reconexión automática cuando expira la sesión de SAP
- **REST API pública**: Nuevos endpoints `/sync-order` y `/sync-products` para integraciones externas
- **Tests expandidos**: 36 tests unitarios (API Client + REST API)

### Mejorado
- Refactor de `API_Client::get()` para delegar en `request()`
- Escape seguro de queries OData con `sapwc_escape_odata()`

---

## [2.8.5] - 2025-01-XX

### Corregido
- Fix paginación de clientes SAP (respeta `$top` y `$skip`)
- Validación de UDFs simplificada (fallback silencioso)

### Mejorado
- Landing page SEO optimizada
- Documentación de instalación

---

## [2.8.0] - 2024-12-XX

### Añadido
- Importador de clientes con paginación
- Soporte para múltiples almacenes por tarifa
- Filtro de grupos de artículos en importación

### Corregido
- Encoding de caracteres especiales en OData

---

## [2.7.0] - 2024-11-XX

### Añadido
- Field Mapper visual para configurar mapeos
- Soporte para UDFs en productos y pedidos
- Validación de UDFs contra esquema SAP

### Mejorado
- Interfaz de configuración reorganizada
- Logs más detallados

---

## [2.6.0] - 2024-10-XX

### Añadido
- Sincronización bidireccional de stock
- Webhook para recibir actualizaciones de SAP
- Modo B2B con CardCode por cliente

### Corregido
- Manejo de errores en conexión SSL

---

## [2.5.0] - 2024-09-XX

### Añadido
- Importador de productos desde SAP
- Soporte para productos variables
- Mapeo de categorías SAP → WC

---

## [2.0.0] - 2024-06-XX

### Añadido
- Arquitectura modular completa
- Sistema de auto-actualizaciones desde GitHub
- Panel de administración rediseñado

### Cambiado
- Requiere PHP 7.4+
- Requiere WooCommerce 6.0+

---

## [1.0.0] - 2024-01-XX

### Lanzamiento inicial
- Conexión con SAP B1 Service Layer
- Sincronización de pedidos a SAP
- Configuración básica de credenciales
