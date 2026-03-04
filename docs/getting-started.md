# Instalación

## Requisitos

- WordPress 5.8 o superior
- WooCommerce 6.0 o superior
- PHP 7.4 o superior
- SAP Business One con Service Layer habilitado
- Acceso HTTPS al servidor SAP (puerto 50000 por defecto)

## Instalación del plugin

### Opción 1: Desde archivo ZIP

1. Descarga el archivo `sap-woo-suite-x.x.x.zip` desde tu cuenta
2. En WordPress, ve a **Plugins → Añadir nuevo → Subir plugin**
3. Selecciona el archivo ZIP y haz clic en **Instalar ahora**
4. Activa el plugin

### Opción 2: Actualizaciones automáticas

El plugin soporta actualizaciones automáticas desde GitHub. Para habilitarlas:

1. Añade tu token de GitHub en `wp-config.php`:
   ```php
   define('SAPWC_GITHUB_TOKEN', 'ghp_tu_token_aqui');
   ```

2. Las actualizaciones aparecerán en **Plugins** como cualquier otro plugin

## Configuración inicial

Tras activar el plugin:

1. Ve a **SAP Woo Suite → Credenciales**
2. Configura la conexión a SAP Business One
3. Prueba la conexión con el botón **Test**
4. Guarda los cambios

## Siguiente paso

→ [Configuración de conexión SAP](configuration.md)
