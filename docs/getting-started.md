# Instalación

## Requisitos

- WordPress 5.8 o superior
- WooCommerce 6.0 o superior
- PHP 8.1 o superior
- SAP Business One con Service Layer habilitado
- Acceso HTTPS al servidor SAP (puerto 50000 por defecto)

## Instalación del plugin

### Opción 1: Desde archivo ZIP

1. Descarga el archivo `sap-woo-suite-x.x.x.zip` desde tu cuenta
2. En WordPress, ve a **Plugins → Añadir nuevo → Subir plugin**
3. Selecciona el archivo ZIP y haz clic en **Instalar ahora**
4. Activa el plugin

### Opción 2: Actualizaciones gestionadas

Las versiones se distribuyen mediante la License API de Replanta y se supervisan desde Plugin Center. El cliente no necesita crear ni guardar tokens personales de GitHub. La licencia y el canal de actualización se configuran durante la implantación.

Antes de actualizar una instalación en producción se comprueba la versión disponible, la conectividad con SAP, el estado del cron y los pedidos pendientes.

## Configuración inicial

Tras activar el plugin:

1. Ve a **Conector de WooCommerce para SAP Business One → Credenciales**
2. Configura la conexión a SAP Business One
3. Prueba la conexión con el botón **Test**
4. Guarda los cambios

## Siguiente paso

→ [Configuración de conexión SAP](configuration.md)
