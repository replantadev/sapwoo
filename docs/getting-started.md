# Primeros Pasos

## Requisitos

- WordPress 5.8 o superior
- WooCommerce 6.0 o superior
- PHP 7.4 o superior
- SAP Business One con Service Layer habilitado
- Acceso HTTPS al servidor SAP (puerto 50000 por defecto)

## Elegir versión

| Característica | Lite | PRO |
|----------------|:----:|:---:|
| Conexión SAP | ✅ | ✅ |
| Sync stock | ✅ | ✅ |
| Sync precios | ✅ | ✅ |
| Importar productos | ❌ | ✅ |
| Sync pedidos | ❌ | ✅ |
| Sync clientes | ❌ | ✅ |
| Mapeo de campos | ❌ | ✅ |
| REST API | ❌ | ✅ |
| Multicanal | ❌ | ✅ |

## Instalación

### SAP Woo Suite Lite (Gratis)

1. En WordPress, ve a **Plugins → Añadir nuevo**
2. Busca "SAP Woo Suite Lite"
3. Instala y activa

### SAP Woo Suite PRO

1. Descarga el ZIP desde tu cuenta o el repositorio privado
2. Ve a **Plugins → Añadir nuevo → Subir plugin**
3. Selecciona el archivo ZIP
4. Instala y activa

> **Nota:** Si ya tienes Lite instalado, PRO lo desactivará automáticamente manteniendo tu configuración.

## Configuración inicial

Tras activar el plugin:

1. Ve a **SAP Woo Suite → Credenciales** (PRO) o **SAP Woo Suite Lite** (Lite)
2. Configura la conexión a SAP Business One:
   - **URL del Service Layer**: `https://tu-servidor:50000/b1s/v1`
   - **Base de datos SAP**
   - **Usuario y contraseña**
3. Prueba la conexión con el botón **Probar conexión**
4. Guarda los cambios

## Siguiente paso

→ [Configuración de conexión SAP](configuration.md)
