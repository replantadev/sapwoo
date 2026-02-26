# Changelog

Todos los cambios notables en SAP Woo Sync se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Versionado Semántico](https://semver.org/lang/es/).

---

## [1.4.1-beta] - 2026-02-26

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
