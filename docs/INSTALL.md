# Guía de Instalación

## SAP Woo Suite Lite (Gratis)

### Instalación desde WordPress.org

1. Ve a **Plugins → Añadir nuevo**
2. Busca "SAP Woo Suite Lite"
3. Haz clic en **Instalar ahora**
4. Activa el plugin

### Instalación manual

1. Descarga el ZIP desde [GitHub](https://github.com/replantadev/sap-woo-suite-lite)
2. Ve a **Plugins → Añadir nuevo → Subir plugin**
3. Selecciona el archivo ZIP
4. Haz clic en **Instalar ahora** y activa

---

## SAP Woo Suite PRO

### 1. Obtener el plugin

El paquete se entrega desde el canal de licencias de Replanta durante la implantación. No es necesario conceder acceso al repositorio privado ni crear un token personal de GitHub.

### 2. Instalar en WordPress

1. Ve a **Plugins → Añadir nuevo → Subir plugin**
2. Selecciona el archivo ZIP descargado
3. Haz clic en **Instalar ahora**
4. Activa el plugin

### 3. Verificar WooCommerce

El plugin requiere WooCommerce activo. Si no está instalado, verás un aviso de error.

---

## Actualizar de Lite a PRO

La actualización es seamless. Al instalar PRO:

1. La versión Lite se desactiva automáticamente
2. Todas las configuraciones se mantienen
3. Los logs existentes se preservan

---

## Actualizaciones gestionadas

Las versiones se distribuyen mediante la License API de Replanta y se supervisan desde Plugin Center. El cliente no necesita crear tokens personales de GitHub ni almacenar credenciales del repositorio en WordPress.

Cada actualización incluye comprobaciones de versión, conectividad SAP, ejecución del cron y pedidos pendientes.

---

## Solución de problemas

### No aparecen actualizaciones

1. Comprueba que la licencia esté activa.
2. Fuerza una comprobación desde Plugin Center.
3. Revisa que WordPress pueda escribir en `wp-content/plugins/`.

### Error al descargar la actualización

- Comprueba la conexión saliente HTTPS del servidor.
- Verifica el estado de la licencia y del canal de actualizaciones.

### Error de permisos de escritura

- WordPress no puede escribir en la carpeta de plugins
- Verifica permisos de `wp-content/plugins/` (755 o 775)

---

## Soporte

- Web: [replanta.net](https://replanta.net)
- Email: info@replanta.net
