=== SAP Woo Sync ===
Contributors: replantadev
Tags: woocommerce, sap, b2b, sync, erp, business one
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.4.7-beta
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integración profesional entre WooCommerce y SAP Business One. Sincroniza pedidos, stock, precios y clientes.

== Description ==

**SAP Woo Sync** conecta tu tienda WooCommerce con SAP Business One a través de Service Layer. 
Desarrollado por [Replanta](https://replanta.es).

= Modos de operación =

* **Ecommerce**: Clientes genéricos por región (Península, Canarias, Portugal)
* **B2B**: Cada usuario WooCommerce = un CardCode en SAP

= Funcionalidades =

* Sincronización de pedidos WooCommerce → SAP (automática o manual)
* Sincronización de stock y precios SAP → WooCommerce
* Importación masiva de productos y categorías desde SAP
* Importación de clientes B2B con email de bienvenida
* Tarifas de precios por región configurables
* Logs detallados de todas las operaciones
* Panel de pedidos fallidos con reintento

= Requisitos =

* WordPress 5.8+
* WooCommerce 6.0+
* PHP 7.4+
* SAP Business One 9.3+ con Service Layer activo

== Installation ==

1. Sube el plugin a `/wp-content/plugins/sap-woo`
2. Activa el plugin en WordPress
3. Configura las credenciales SAP en **SAP Woo → Credenciales SAP**
4. Ajusta las opciones en **SAP Woo → Sincronización**

== Frequently Asked Questions ==

= ¿Qué versiones de SAP son compatibles? =

SAP Business One 9.3 PL14 o superior, y SAP Business One 10.0 (todas las versiones).

= ¿Necesito Service Layer? =

Sí, el plugin se conecta a SAP exclusivamente a través de Service Layer (API REST).

= ¿Funciona con certificados autofirmados? =

Sí, puedes desactivar la verificación SSL en la configuración si usas certificado autofirmado.

= ¿Puedo usar el plugin en varias tiendas? =

Sí, cada instalación de WordPress puede conectarse a una base de datos SAP diferente.

== Screenshots ==

1. Panel de pedidos con estado de sincronización
2. Configuración de credenciales SAP
3. Opciones de sincronización
4. Importación de catálogo desde SAP
5. Logs de sincronización

== Changelog ==

= 1.4.4-beta =
* Fix: Sistema de actualización ahora usa GitHub Releases (soluciona problemas con repos privados)
* Mejor compatibilidad con tokens de acceso

= 1.4.3-beta =
* Unificación de menús: Importación Selectiva integrada en página de Importación
* Fix: Fallback de filtros en productos SAP (Valid/ItemType dinámico)
* Fix: Clientes pendientes ahora funcionan sin UDF configurado
* Modal de preview para ver campos antes de importar
* Importación individual y en lote desde tablas de pendientes
* Mejorado el manejo de errores y mensajes

= 1.4.2-beta =
* Nueva página de Importación Selectiva con preview de campos
* Listas de productos, categorías y clientes pendientes de importar
* Importación individual o en lote con progreso visual

= 1.4.0-beta =
* Nueva página de Importación Unificada con 3 pestañas
* Importación por lotes con barra de progreso
* Guía de operario integrada
* Emojis reemplazados por dashicons nativos de WordPress
* Mejoras generales de interfaz

= 1.3.0-beta =
* Sincronización automática de clientes SAP → WooCommerce (B2B)
* Email de bienvenida personalizado con link para establecer contraseña
* Campo UDF configurable para marcar clientes web
* Cron diario para sincronización de clientes

= 1.4.6-beta =
* Eliminado campo FrozenFor (no disponible en todas las versiones de SAP)
* Fix importación selectiva

= 1.4.5-beta =
* Corregido problema de carpeta durante actualizaciones desde GitHub
* Renombrado automático de carpeta sapwoo → sap-woo

= 1.2.79 =
* Mejoras de estabilidad
* Correcciones de bugs menores

== Upgrade Notice ==

= 1.4.0-beta =
Nueva interfaz de importación más intuitiva. Recomendado actualizar.

= 1.3.0-beta =
Añade sincronización automática de clientes B2B. Requiere configurar UDF en SAP.