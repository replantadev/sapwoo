<p align="center">
  <img src="https://replanta.net/wp-content/uploads/2026/03/sapwoosuite-ico.png" alt="SAP Woo Suite" width="100">
</p>

<h1 align="center">SAP Woo Suite</h1>

<p align="center">
  <strong>Conector profesional entre WooCommerce y SAP Business One</strong><br>
  Pedidos, stock, precios, clientes y cat&aacute;logo &mdash; sincronizados autom&aacute;ticamente v&iacute;a Service Layer.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/versi%C3%B3n-2.10.0-0d2a1e" alt="v2.8.5">
  <img src="https://img.shields.io/badge/WooCommerce-6.0%E2%80%939.x-7f54b3" alt="WooCommerce 6.0-9.x">
  <img src="https://img.shields.io/badge/SAP%20B1-9.3%20%7C%2010.0-e97222" alt="SAP Business One">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/HPOS-Compatible-46b450" alt="HPOS Compatible">
  <img src="https://img.shields.io/badge/multicanal-TikTok%20%C2%B7%20Amazon-41999F" alt="Multicanal">
  <img src="https://img.shields.io/badge/licencia-GPLv2-green" alt="GPLv2">
</p>

<p align="center">
  <a href="https://replanta.net/conector-sap-woocommerce/"><strong>Informaci&oacute;n del producto</strong></a> &nbsp;&middot;&nbsp;
  <a href="https://replantadev.github.io/sap-woo-suite-info/"><strong>Especificaciones t&eacute;cnicas</strong></a> &nbsp;&middot;&nbsp;
  <a href="https://replanta.net/wordpress-plugins/"><strong>Todos los plugins</strong></a> &nbsp;&middot;&nbsp;
  <a href="https://replanta.net/contacto"><strong>Solicitar demo</strong></a>
</p>

---

## Qu&eacute; es SAP Woo Suite

**SAP Woo Suite** es un plugin de WordPress que conecta WooCommerce con SAP Business One a trav&eacute;s de **Service Layer**. Elimina la copia manual de datos entre tu tienda online y tu ERP.

Cada pedido que entra en WooCommerce se env&iacute;a autom&aacute;ticamente a SAP como documento de venta. Cada precio o stock que cambia en SAP se refleja en la tienda. Cada cliente B2B de SAP tiene su cuenta en WooCommerce con precios personalizados.

Desde la versi&oacute;n 2.0, SAP Woo Suite incluye una **arquitectura multicanal** con Channel Manager extensible. Desde la 2.1, **detecci&oacute;n autom&aacute;tica de canal** por metadata: los pedidos de TikTok Shop y Amazon se etiquetan y documentan en SAP sin configuraci&oacute;n adicional.

> **Este repositorio es la p&aacute;gina p&uacute;blica del proyecto.** El c&oacute;digo fuente del plugin se distribuye de forma privada con la instalaci&oacute;n. Consulta las [especificaciones completas](https://replantadev.github.io/sap-woo-suite-info/) o la [p&aacute;gina del producto](https://replanta.net/conector-sap-woocommerce/) para m&aacute;s informaci&oacute;n.

---

## Funcionalidades

### Pedidos WooCommerce &rarr; SAP Business One
- Env&iacute;o autom&aacute;tico o manual de pedidos como documentos de venta (Orders)
- Asignaci&oacute;n inteligente de cliente por regi&oacute;n geogr&aacute;fica (modo Ecommerce)
- Mapeo de direcciones de facturaci&oacute;n y env&iacute;o a SAP
- Gastos de env&iacute;o como DocumentAdditionalExpenses
- Notas de pedido enriquecidas con datos del cliente
- Panel de pedidos fallidos con reintento individual

### Stock y precios SAP &rarr; WooCommerce
- Importaci&oacute;n de stock por almac&eacute;n configurable
- Tarifas de precios por regi&oacute;n (Pen&iacute;nsula, Canarias, Portugal u otras)
- Mapeo almac&eacute;n-tarifa personalizable desde el admin
- Sincronizaci&oacute;n programable con toggles independientes para stock y precios

### Cat&aacute;logo completo SAP &rarr; WooCommerce
- Importaci&oacute;n masiva de productos (Items) por lotes con barra de progreso
- Importaci&oacute;n de categor&iacute;as (ItemGroups)
- Preview de campos antes de importar
- Logs en tiempo real de cada operaci&oacute;n

### Clientes B2B
- Sincronizaci&oacute;n autom&aacute;tica diaria de socios de negocio marcados en SAP
- Cada usuario WooCommerce = un CardCode en SAP
- Email de bienvenida personalizado con enlace para establecer contrase&ntilde;a
- Detecci&oacute;n de usuarios existentes por email (vinculaci&oacute;n sin duplicados)
- Cola de emails v&iacute;a Action Scheduler (no sobrecarga el servidor)

### Multicanal (desde v2.1)
- **Channel Manager** extensible con clase base para addons
- Detecci&oacute;n autom&aacute;tica de canal por metadata del pedido
- TikTok Shop y Amazon v&iacute;a plugins oficiales &mdash; sin configuraci&oacute;n extra
- Miravia disponible como addon
- Cada pedido queda etiquetado en SAP con su canal de origen

### Logs y diagn&oacute;stico
- Registro detallado de cada sincronizaci&oacute;n (pedidos, stock, precios, clientes)
- Panel de pedidos fallidos con vista directa del error y opci&oacute;n de reintento
- Validaci&oacute;n de campos UDF en SAP desde el panel de WordPress
- Vista de documentos SAP desde el admin de WooCommerce

---

## Modos de operaci&oacute;n

| Modo | Descripci&oacute;n | Caso de uso |
|------|------|------|
| **Ecommerce (B2C)** | Clientes gen&eacute;ricos por regi&oacute;n geogr&aacute;fica | Tiendas abiertas al p&uacute;blico con pedidos de consumidor final |
| **B2B (Mayorista)** | Cada usuario WooCommerce = un CardCode en SAP | Portales de distribuidores con precios por tarifa personalizada |

Cambia de modo en cualquier momento desde el panel de administraci&oacute;n.

---

## Arquitectura y flujo de datos

```
WooCommerce                          SAP Business One
===========                          ================

Nuevo pedido â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â–º Order (DocEntry)
                                     + DocumentLines
                                     + AddressExtension
                                     + DocumentAdditionalExpenses

Stock actualizado â—„â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ Items (WarehouseInfo)
Precios actualizados â—„â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ PriceLists (SpecialPrices)

Productos importados â—„â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ Items
Categor&iacute;as importadas â—„â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ ItemGroups
Clientes B2B sincronizados â—„â”€â”€â”€â”€â”€â”€ BusinessPartners

TikTok Shop  â”€â”€â–º Channel Manager â”€â”€â–º Order (canal: tiktok)
Amazon       â”€â”€â–º Channel Manager â”€â”€â–º Order (canal: amazon)
Miravia      â”€â”€â–º Channel Manager â”€â”€â–º Order (canal: miravia)
```

Comunicaci&oacute;n bidireccional a trav&eacute;s de SAP Service Layer (REST/OData). Sin middleware, sin servicios externos, sin cuotas mensuales por volumen de pedidos.

---

## 14 hooks de extensi&oacute;n

Personaliza cada aspecto de la sincronizaci&oacute;n sin modificar el c&oacute;digo fuente del plugin.

| Hook | Tipo | Descripci&oacute;n |
|------|------|------|
| `sapwc_loaded` | action | Plugin cargado &mdash; registrar canales |
| `sapwc_order_payload` | filter | Modificar payload antes de enviar a SAP |
| `sapwc_before_send_order` | action | Antes de enviar pedido |
| `sapwc_after_send_order` | action | Despu&eacute;s de enviar pedido |
| `sapwc_before_save_product` | filter | Modificar producto antes de guardar |
| `sapwc_after_import_product` | action | Despu&eacute;s de importar producto |
| `sapwc_after_import_customer` | action | Despu&eacute;s de importar cliente |
| `sapwc_documents_owner` | filter | Personalizar DocumentsOwner |
| `sapwc_api_request_args` | filter | Modificar petici&oacute;n a Service Layer |
| `sapwc_admin_menu` | action | A&ntilde;adir submen&uacute;s personalizados |
| `sapwc_channel_registered` | action | Canal registrado en Channel Manager |
| `sapwc_channel_detectors` | filter | Registrar detectores de canal propios |
| `sapwc_channel_payload` | filter | Modificar payload tras inyectar info de canal |
| `sapwc_builtin_channels_registered` | action | Canales built-in registrados |

Documentaci&oacute;n completa de hooks en las [especificaciones t&eacute;cnicas](https://replantadev.github.io/sap-woo-suite-info/#developers).

---

## Requisitos t&eacute;cnicos

### WordPress / WooCommerce
- WordPress 5.8+
- WooCommerce 6.0+ (hasta 9.x)
- PHP 7.4+ (recomendado 8.0+)
- Compatible con HPOS (High-Performance Order Storage)

### SAP Business One
- SAP B1 9.3 PL14+ o 10.0+
- Service Layer activo (`/b1s/v1` accesible v&iacute;a HTTPS)
- Permisos en Orders, BusinessPartners, Items, ItemGroups, PriceLists
- UDFs opcionales para funcionalidades avanzadas (clientes B2B, canales)

---

## Instalaci&oacute;n

SAP Woo Suite se distribuye como plugin privado de WordPress (ZIP). La instalaci&oacute;n incluye:

1. **Configuraci&oacute;n asistida** &mdash; conectamos tu Service Layer, definimos modo de operaci&oacute;n y mapeamos almacenes/tarifas
2. **Importaci&oacute;n inicial** &mdash; cat&aacute;logo, categor&iacute;as y clientes B2B si aplica
3. **Verificaci&oacute;n** &mdash; primer pedido de prueba sincronizado con SAP
4. **Actualizaciones autom&aacute;ticas** &mdash; el plugin se actualiza desde GitHub como cualquier plugin de WordPress

> No se requiere middleware, servidores intermedios ni integraciones de terceros.

---

## Precios

| Concepto | |
|----------|---|
| **Setup e instalaci&oacute;n** | desde 1.500 EUR |
| **Mantenimiento anual** | desde 900 EUR/a&ntilde;o |
| **Addon Miravia** | consultar |

El setup incluye la instalaci&oacute;n del plugin, configuraci&oacute;n de Service Layer, importaci&oacute;n inicial del cat&aacute;logo y verificaci&oacute;n del flujo completo de pedidos.

<p align="center">
  <a href="https://replanta.net/conector-sap-woocommerce/#rep-plugins-pricing">Ver precios detallados</a> &nbsp;&middot;&nbsp;
  <a href="https://replanta.net/contacto">Solicitar presupuesto</a>
</p>

---

## Enlaces

| | |
|---|---|
| Producto | [replanta.net/conector-sap-woocommerce](https://replanta.net/conector-sap-woocommerce/) |
| Especificaciones | [replantadev.github.io/sap-woo-suite-info](https://replantadev.github.io/sap-woo-suite-info/) |
| Todos los plugins | [replanta.net/wordpress-plugins](https://replanta.net/wordpress-plugins/) |
| Contacto | [replanta.net/contacto](https://replanta.net/contacto) |
| Email | [info@replanta.net](mailto:info@replanta.net) |

---

## Otros plugins de Replanta

| Plugin | Descripci&oacute;n | |
|--------|------|---|
| **[DNIWOO Validator](https://github.com/replantadev/dniwoo)** | Validaci&oacute;n de DNI/NIE/CIF/NIF en checkout de WooCommerce | Gratuito &middot; GPL |
| **[Sello Replanta](https://github.com/replantadev/selloreplanta)** | Sello Carbon Negative autom&aacute;tico para WooCommerce | Gratuito &middot; GPL |

---

## Licencia

SAP Woo Suite est&aacute; licenciado bajo GPLv2 o posterior.

```
SAP Woo Suite - Conector WooCommerce <-> SAP Business One
Copyright (C) 2024-2026 Replanta

Este programa es software libre: puedes redistribuirlo y/o modificarlo
bajo los t&eacute;rminos de la Licencia P&uacute;blica General GNU publicada por
la Free Software Foundation, versi&oacute;n 2 de la Licencia o posterior.
```

---

<p align="center">
  <a href="https://replanta.net">replanta.net</a> &nbsp;&middot;&nbsp; <a href="mailto:info@replanta.net">info@replanta.net</a> &nbsp;&middot;&nbsp; <a href="https://github.com/replantadev">@replantadev</a>
</p>
