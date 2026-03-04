# SAP Woo Suite

> Integración completa entre WooCommerce y SAP Business One

## ¿Qué es SAP Woo Suite?

SAP Woo Suite es un plugin de WordPress que conecta tu tienda WooCommerce con SAP Business One a través del Service Layer, permitiendo:

- **Sincronización de pedidos** → Envía pedidos de WC a SAP automáticamente
- **Importación de productos** → Trae tu catálogo de SAP a WooCommerce
- **Sincronización de stock** → Mantén el inventario actualizado en tiempo real
- **Gestión de clientes** → Sincroniza clientes entre ambas plataformas
- **Tarifas regionales** → Precios diferentes por zona geográfica

## Características principales

### Sincronización bidireccional

- Pedidos WooCommerce → Documentos SAP
- Stock SAP → Disponibilidad WooCommerce
- Productos SAP → Catálogo WooCommerce

### Configuración flexible

- Modos B2B y B2C
- Mapeo de campos personalizable
- Soporte para UDFs (User Defined Fields)
- Múltiples almacenes y tarifas

### API REST

Endpoints públicos para integraciones externas:
- `/wp-json/sapwc/v1/sync-order`
- `/wp-json/sapwc/v1/sync-products`

### Seguridad

- Autenticación segura con Service Layer
- Reconexión automática (Retry 401)
- Validación de datos antes de sincronizar

## Requisitos

| Componente | Versión mínima |
|------------|----------------|
| WordPress | 5.8+ |
| WooCommerce | 6.0+ |
| PHP | 7.4+ |
| SAP Business One | 9.3+ (Service Layer) |

## Empezar

→ [Guía de inicio rápido](getting-started.md)

## Soporte

- **Email**: soporte@replanta.dev
- **GitHub**: [Issues](https://github.com/replantadev/sap-woo-suite/issues)
