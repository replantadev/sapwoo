<p align="center">
  <img src="https://replanta.es/wp-content/uploads/2023/01/replanta-logo.png" alt="Replanta" width="200">
</p>

<h1 align="center">SAP Woo Sync</h1>

<p align="center">
  <strong>Integración profesional entre WooCommerce y SAP Business One</strong><br>
  Desarrollado por <a href="https://replanta.es">Replanta</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/versión-1.4.0--beta-blue" alt="Versión">
  <img src="https://img.shields.io/badge/WooCommerce-6.0%2B-purple" alt="WooCommerce">
  <img src="https://img.shields.io/badge/SAP%20B1-9.3%20%7C%2010.0-orange" alt="SAP B1">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4" alt="PHP">
  <img src="https://img.shields.io/badge/licencia-GPLv2-green" alt="Licencia">
</p>

---

## 📋 Descripción

**SAP Woo Sync** es un plugin de WordPress que conecta tu tienda WooCommerce con SAP Business One a través de Service Layer. Permite sincronizar pedidos, stock, precios, clientes y catálogo de productos de forma bidireccional.

### Modos de operación

| Modo | Descripción | Uso típico |
|------|-------------|------------|
| **Ecommerce** | Clientes genéricos por región (Península, Canarias, Portugal) | Tiendas B2C con cliente anónimo |
| **B2B** | Cada usuario WooCommerce = un CardCode en SAP | Portales mayoristas con precios personalizados |

---

## ✨ Funcionalidades principales

### Sincronización de Pedidos (WooCommerce → SAP)
- Envío automático o manual de pedidos a SAP como documentos de venta
- Asignación inteligente de cliente por región geográfica
- Mapeo de direcciones de facturación y envío
- Soporte para gastos de envío (DocumentAdditionalExpenses)
- Notas de pedido enriquecidas con datos del cliente

### Sincronización de Stock y Precios (SAP → WooCommerce)
- Importación de stock por almacén configurable
- Tarifas de precios por región (Península, Canarias, Portugal)
- Mapeo de almacén-tarifa personalizable
- Sincronización automática programable (toggles independientes)

### Importación de Catálogo (SAP → WooCommerce)
- Importación masiva de productos (Items) por lotes
- Importación de categorías (ItemGroups)
- Importación de clientes (BusinessPartners) en modo B2B
- Barra de progreso y logs en tiempo real

### Gestión de Clientes B2B
- Sincronización automática de clientes marcados como "web" en SAP
- Email de bienvenida personalizado con enlace para establecer contraseña
- Mapeo flexible de CardCode (user_login, meta field, etc.)

---

## 🔧 Requisitos

### WordPress / WooCommerce
- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+ (recomendado 8.0+)

### SAP Business One
- SAP Business One 9.3 PL14+ o 10.0+
- **Service Layer activo** (`/b1s/v1` accesible vía HTTPS)
- Usuario de Service Layer con permisos de lectura/escritura en:
  - Orders (Pedidos de venta)
  - BusinessPartners (Socios de negocio)
  - Items (Artículos)
  - ItemGroups (Grupos de artículos)
  - PriceLists (Listas de precios)

### Campos UDF requeridos (según configuración)
El plugin utiliza campos definidos por el usuario (UDF) para funcionalidades avanzadas. Consulta con tu consultor SAP para crearlos si no existen.

---

## 📦 Instalación

### Instalación manual
1. Descarga el archivo ZIP del plugin
2. Sube a `wp-content/plugins/sap-woo/`
3. Activa desde **Plugins** en el panel de WordPress

### Actualizaciones automáticas
Este plugin se actualiza automáticamente desde un repositorio privado. El administrador del sitio recibirá notificaciones de actualización en el panel de WordPress.

---

## ⚙️ Configuración inicial

### 1. Credenciales SAP
Ve a **SAP Woo Sync → Credenciales SAP** e introduce:
- URL de Service Layer (ej: `https://sap.tuempresa.com:50000/b1s/v1`)
- Usuario y contraseña de Service Layer
- Base de datos (CompanyDB)
- Verificación SSL (activar si usas certificado válido)

### 2. Opciones de Sincronización
En **SAP Woo Sync → Sincronización**:
- Selecciona el **modo** (Ecommerce o B2B)
- Configura los **clientes genéricos** por región (modo Ecommerce)
- Define los **filtros de cliente** (modo B2B)
- Selecciona la **tarifa de precios** a usar
- Configura los **almacenes** para stock

### 3. Importación inicial
En **SAP Woo Sync → Importación**:
1. Importa primero las **Categorías** (para que se vinculen a productos)
2. Importa los **Productos** con las opciones deseadas
3. (Solo B2B) Importa los **Clientes** si es necesario

---

## 📊 Panel de control

| Sección | Descripción |
|---------|-------------|
| **Pedidos** | Lista de pedidos WooCommerce con estado de sincronización SAP |
| **Pedidos SAP** | Vista de los últimos pedidos directamente desde SAP |
| **Importación** | Herramientas para importar catálogo desde SAP |
| **Logs** | Registro detallado de todas las operaciones |
| **Pedidos Fallidos** | Pedidos que no pudieron enviarse a SAP (con opción de reintento) |

---

## 🔄 Flujo de sincronización

```
┌─────────────────┐         ┌─────────────────┐
│   WooCommerce   │         │  SAP Business   │
│                 │         │      One        │
├─────────────────┤         ├─────────────────┤
│                 │         │                 │
│  Nuevo Pedido   │────────▶│  Order creado   │
│                 │         │  (DocEntry)     │
│                 │         │                 │
│  Stock/Precios  │◀────────│  Items/Prices   │
│  actualizados   │         │                 │
│                 │         │                 │
│  Productos      │◀────────│  Items          │
│  importados     │         │                 │
│                 │         │                 │
│  Categorías     │◀────────│  ItemGroups     │
│  importadas     │         │                 │
│                 │         │                 │
│  Clientes B2B   │◀────────│  BusinessPart.  │
│  sincronizados  │         │                 │
└─────────────────┘         └─────────────────┘
```

---

## 🐛 Solución de problemas

### "No hay conexión activa con SAP"
- Verifica que la URL de Service Layer sea correcta y accesible
- Comprueba las credenciales (usuario/contraseña/BD)
- Si usas certificado autofirmado, desactiva "Verificación SSL"

### Los pedidos no se envían automáticamente
- Revisa que el toggle "Envío automático" esté activado en Sincronización
- Los pedidos solo se envían cuando pasan a estado "Procesando"
- Verifica los logs para ver errores específicos

### Stock no se actualiza
- Comprueba que el SKU del producto en WooCommerce coincida con ItemCode en SAP
- Verifica que el almacén configurado tenga stock disponible
- El toggle de sincronización de stock debe estar activado

---

## 📞 Soporte

Este plugin es desarrollado y mantenido por **Replanta**.

- 🌐 Web: [replanta.es](https://replanta.es)
- 📧 Email: soporte@replanta.es
- 📍 Ubicación: España

---

## 📜 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para el historial completo de versiones.

### Últimas versiones
- **v1.4.0-beta** - Importación unificada, dashicons, guía de operario
- **v1.3.0-beta** - Sincronización de clientes B2B, email de bienvenida
- **v1.2.79** - Mejoras de estabilidad

---

## 📄 Licencia

Este plugin está licenciado bajo GPLv2 o posterior.

```
SAP Woo Sync - Integración WooCommerce ↔ SAP Business One
Copyright (C) 2024-2026 Replanta

Este programa es software libre: puedes redistribuirlo y/o modificarlo
bajo los términos de la Licencia Pública General GNU publicada por
la Free Software Foundation, ya sea la versión 2 de la Licencia, o
cualquier versión posterior.
```

---

<p align="center">
  <sub>Desarrollado con cuidado por <a href="https://replanta.es">Replanta</a></sub>
</p>
