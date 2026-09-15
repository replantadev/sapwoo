# Conector de WooCommerce para SAP Business One

> Plataforma multicanal para SAP Business One. Sincroniza WooCommerce y marketplaces con SAP B1 vía Service Layer.

**Versión actual: 2.22.28** · estable · [ver planes y precios](https://replanta.net/conector-sap-woocommerce/) · [changelog completo](changelog.md)

## ¿Qué es el conector de WooCommerce para SAP Business One?

El conector de WooCommerce para SAP Business One es un plugin premium de WordPress/WooCommerce que conecta tu tienda online con SAP Business One de forma bidireccional:

- **Pedidos WC → SAP**: sincronización automática de pedidos al procesarse.
- **Productos SAP → WC**: importación masiva con delta sync (UpdateDate) y mapeo de campos personalizable.
- **Stock SAP → WC**: webhook para actualizaciones en tiempo real.
- **Clientes SAP → WC**: importación con Action Scheduler + emails de bienvenida.
- **Portal de facturas**: los clientes Ecommerce y B2B consultan y descargan sus facturas desde Mi cuenta de WooCommerce.
- **Promociones B2B**: tratamiento de unidades sin cargo e integración con YITH Dynamic Pricing & Discounts.
- **Multicanal**: soporte para TikTok Shop, Amazon y Miravia.
- **Plugin Center**: gestión, vigilancia, logs, actualizaciones y rotación de secretos desde un panel central.

## Características principales

| Feature | Descripción |
|---------|-------------|
| **Sync automático** | Pedidos se envían a SAP al cambiar de estado |
| **Retry inteligente** | Action Scheduler con back-off exponencial y cola de reintentos |
| **Idempotencia** | Evita duplicados verificando en SAP antes de crear |
| **Delta sync de catálogo** | Solo importa artículos modificados (`UpdateDate`) |
| **Portal de facturas** | Botón de descarga en Mis pedidos + pestaña Mis facturas |
| **Dashboard multicanal** | Vista unificada de WooCommerce + marketplaces |
| **REST API** | Endpoints públicos para integraciones externas |
| **Control API** | Operaciones remotas autenticadas para Plugin Center |
| **Cifrado AES-256-CBC** | Credenciales SAP con IV aleatorio por instalación |
| **Logs detallados** | Historial completo con rotación automática |
| **Integridad de pedidos** | Verificación posterior de dirección, promociones e idempotencia |

## Novedades recientes

- **2.22.28**: pedidos B2B reforzados con unidades sin cargo, verificación de direcciones y diagnóstico fiable de incidencias.
- **2.22.0 a 2.22.27**: portal de facturas Ecommerce y B2B, generación PDF, actualización gestionada y mejoras de compatibilidad.
- **2.20.0**: Portal B2B, descarga de facturas SAP desde Mi cuenta de WooCommerce con fallback automático (adjunto SAP → layout export → filtros de integración).
- **2.19.3**: Endpoint `POST /control/mark-exported` para marcar como exportado manualmente desde el Control Center.
- **2.15.16**: Rate limiter reforzado (ventana fija, sin spoofing X-Forwarded-For), rate limit en `/stock-update` y timestamps UTC consistentes.
- **2.15.14**: Auto-Sync de catálogo con delta por `UpdateDate` y extensión de Etiquetas / Albaranes SAP.
- **2.15.9 a 2.15.12**: Control API, endpoints `update`, `rotate-secret`, `clear-cache`, `run-cron`, `maintenance`, `update-check`, `logs`.

Ver el [changelog completo](changelog.md) para todas las versiones.

## Primeros pasos

- [Instalación](getting-started.md)
- [Configuración de conexión SAP](configuration.md)
- [Mapeo de campos](field-mapping.md)

## Guías

- [Sincronización de pedidos](guides/sync-orders.md)
- [Importación de productos](guides/import-products.md)
- [Importación de clientes](guides/import-customers.md)
- [Portal de facturas](guides/b2b-invoices.md)
- [REST API](guides/rest-api.md)

## Soporte

- Web: [replanta.net/conector-sap-woocommerce](https://replanta.net/conector-sap-woocommerce/)
- Contacto: [replanta.net/contacto](https://replanta.net/contacto/)

