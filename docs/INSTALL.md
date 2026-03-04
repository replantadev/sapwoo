# Guía de Instalación - SAP Woo Sync

## Instalación inicial

### 1. Obtener el plugin

Descarga el archivo ZIP de la última release desde el repositorio privado de GitHub (requiere acceso).

### 2. Instalar en WordPress

1. Ve a **Plugins → Añadir nuevo → Subir plugin**
2. Selecciona el archivo ZIP descargado
3. Haz clic en **Instalar ahora**
4. Activa el plugin

### 3. Verificar WooCommerce

El plugin requiere WooCommerce activo. Si no está instalado, verás un aviso de error.

---

## Configurar actualizaciones automáticas (repo privado)

Para recibir actualizaciones automáticas desde el repositorio privado de GitHub, necesitas configurar un token de acceso.

### Paso 1: Crear token en GitHub

1. Ve a [GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)](https://github.com/settings/tokens)
2. Haz clic en **Generate new token (classic)**
3. Nombre: `SAPWOO Updates` (o similar)
4. Expiration: Recomendado **No expiration** para evitar problemas
5. Permisos requeridos:
   - `repo` (acceso completo a repos privados)
6. Genera el token y **cópialo inmediatamente** (no podrás verlo de nuevo)

### Paso 2: Añadir token a WordPress

Edita el archivo `wp-config.php` de tu instalación de WordPress y añade esta línea **ANTES** de `/* That's all, stop editing! */`:

```php
/** Token de GitHub para actualizaciones de SAP Woo Sync */
define('SAPWC_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
```

Reemplaza `ghp_xxxx...` con tu token real.

### Paso 3: Verificar

1. Ve a **Plugins** en WordPress
2. Deberías ver el aviso de actualización cuando haya una nueva versión disponible
3. Las actualizaciones funcionarán automáticamente

---

## Alternativa: Token como constante de entorno

Si prefieres no poner el token en `wp-config.php`, puedes definirlo como variable de entorno en tu servidor:

```bash
# En .htaccess (Apache) o configuración de nginx
SetEnv SAPWC_GITHUB_TOKEN ghp_xxxxxxxxxxxx
```

Y en `wp-config.php`:

```php
if (getenv('SAPWC_GITHUB_TOKEN')) {
    define('SAPWC_GITHUB_TOKEN', getenv('SAPWC_GITHUB_TOKEN'));
}
```

---

## Solución de problemas

### No aparecen actualizaciones

1. Verifica que el token tenga permisos `repo`
2. Comprueba que el token no haya expirado
3. Verifica que la constante esté definida correctamente:
   ```php
   // Añade esto temporalmente para debug
   error_log('SAPWC Token: ' . (defined('SAPWC_GITHUB_TOKEN') ? 'Definido' : 'NO definido'));
   ```

### Error "Not found" al actualizar

- El repositorio es privado y el token no tiene acceso
- Verifica que el token pertenezca a una cuenta con acceso al repo

### Error de permisos de escritura

- WordPress no puede escribir en la carpeta de plugins
- Verifica permisos de `wp-content/plugins/` (755 o 775)

---

## Soporte

- Web: [replanta.es](https://replanta.es)
- Email: info@replanta.dev
