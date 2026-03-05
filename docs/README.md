# SAP Woo Suite

<p align="center">
  <img src="https://replanta.net/wp-content/uploads/2026/03/sapwoosuite-ico.png" alt="SAP Woo Suite" width="120">
</p>

<p align="center">
  <strong>Integración WooCommerce con SAP Business One</strong>
</p>

<p align="center">
  <a href="https://github.com/replantadev/sap-woo-suite-lite"><img src="https://img.shields.io/badge/Lite-v1.2.3-41999f?style=for-the-badge&logo=wordpress" alt="Lite v1.2.3"></a>
  <a href="https://github.com/replantadev/sap-woo-suite"><img src="https://img.shields.io/badge/PRO-v2.10.1-1e2f23?style=for-the-badge&logo=sap" alt="PRO v2.10.1"></a>
</p>

---

## ¿Qué es SAP Woo Suite?

SAP Woo Suite es un plugin de WordPress que conecta tu tienda WooCommerce con SAP Business One a través del Service Layer, permitiendo:

- **Sincronización de pedidos** → Envía pedidos de WC a SAP automáticamente
- **Importación de productos** → Trae tu catálogo de SAP a WooCommerce
- **Sincronización de stock** → Mantén el inventario actualizado en tiempo real
- **Gestión de clientes** → Sincroniza clientes entre ambas plataformas
- **Tarifas regionales** → Precios diferentes por zona geográfica

## Versiones disponibles

### SAP Woo Suite Lite (Gratis)

Version gratuita disponible en WordPress.org con funcionalidad basica:

- Conexion a SAP Business One via Service Layer
- Sincronizacion de stock desde SAP
- Sincronizacion de precios por tarifa
- Logs basicos de sincronizacion

<a href="https://github.com/replantadev/sap-woo-suite-lite">Descargar Lite</a>

### SAP Woo Suite PRO

Version completa con todas las caracteristicas para empresas:

- Todo lo incluido en Lite
- Importacion completa de productos
- Sincronizacion de pedidos a SAP
- Sincronizacion de clientes
- Mapeo de campos personalizable
- Multiple warehouses y tarifas
- REST API para integraciones
- Soporte multicanal (TikTok, Amazon, eBay)

<a href="https://replanta.net/conector-sap-woocommerce/">Obtener PRO</a>

---

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

- **Email**: info@replanta.dev
- **GitHub**: [Issues](https://github.com/replantadev/sap-woo-suite/issues)
