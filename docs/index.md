# SAP Woo Suite

> Plataforma multicanal para SAP Business One. Sincroniza WooCommerce y marketplaces con SAP B1 via Service Layer.

## ¿Qué es SAP Woo Suite?

SAP Woo Suite es un plugin premium de WordPress/WooCommerce que conecta tu tienda online con SAP Business One de forma bidireccional:

- **Pedidos WC → SAP**: Sincronización automática de pedidos al procesarse
- **Productos SAP → WC**: Importación masiva con mapeo de campos personalizable
- **Stock SAP → WC**: Webhook para actualizaciones en tiempo real
- **Clientes SAP → WC**: Importación con Action Scheduler + emails de bienvenida
- **Multicanal**: Soporte para TikTok Shop, Amazon, eBay, Miravia

## Características principales

| Feature | Descripción |
|---------|-------------|
| **Sync automático** | Pedidos se envían a SAP al cambiar de estado |
| **Retry inteligente** | Action Scheduler con back-off exponencial |
| **Idempotencia** | Evita duplicados verificando en SAP antes de crear |
| **Dashboard multicanal** | Vista unificada de todos los canales |
| **REST API** | Endpoints para integraciones externas |
| **Logs detallados** | Historial completo con rotación automática |

## Primeros pasos

- [Instalación](getting-started.md)
- [Configuración de conexión SAP](configuration.md)
- [Mapeo de campos](field-mapping.md)

## Guías

- [Sincronización de pedidos](guides/sync-orders.md)
- [Importación de productos](guides/import-products.md)
- [Importación de clientes](guides/import-customers.md)
- [REST API](guides/rest-api.md)

## Soporte

- Email: soporte@replanta.net
- Web: [replanta.net](https://replanta.net)
