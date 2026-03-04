# Configuración de conexión SAP

## Credenciales de Service Layer

Para conectar con SAP Business One necesitas:

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **URL del servidor** | Dirección del Service Layer | `https://sap.tuempresa.com:50000` |
| **Usuario** | Usuario de SAP B1 | `manager` |
| **Contraseña** | Contraseña del usuario | `********` |
| **Base de datos** | Nombre de la company DB | `SBO_DEMO_ES` |
| **Verificar SSL** | Validar certificado SSL | Si (produccion) |

## Modos de operación

### Modo E-commerce (B2C)

Para tiendas que venden a consumidores finales:

- Todos los pedidos se asignan a un **CardCode genérico** por región
- Configura los CardCodes en **Opciones de Sincronización**:
  - Península: `WNAD PENINSULA`
  - Canarias: `WNAD CANARIAS`
  - Portugal: `WWEB PORTUGAL`

### Modo B2B

Para tiendas mayoristas:

- Cada cliente WooCommerce tiene su propio **CardCode** de SAP
- El CardCode se almacena en el meta del usuario: `_sap_card_code`
- Filtrado de clientes por prefijo (ej: `C` para clientes que empiezan por C)

## Tarifas regionales

Asigna diferentes listas de precios según la región de entrega:

| Región | Tarifa SAP | IVA |
|--------|-----------|-----|
| Península + Baleares | Configurable | Si |
| Canarias | Configurable | No (IGIC) |
| Portugal | Configurable | Si |

## Mapeo de almacenes

Si tienes varios almacenes, puedes asignar tarifas específicas:

```
Almacén 01 → Tarifa 1
Almacén 02 → Tarifa 2
```

## Probar la conexión

1. Haz clic en **Probar conexión**
2. Verifica que aparezca:
   - Conexion exitosa
   - Version de Service Layer
   - Nombre de la base de datos

## Solución de problemas

### Error: "No se puede conectar"

- Verifica que el puerto 50000 esté abierto
- Comprueba la URL (incluye `https://`)
- Revisa que el usuario tenga permisos en Service Layer

### Error: "Certificado inválido"

- En desarrollo: Desmarca "Verificar SSL"
- En producción: Instala un certificado válido

### Error: "Usuario o contraseña incorrectos"

- El usuario debe existir en SAP B1
- La contraseña es sensible a mayúsculas

## Siguiente paso

→ [Mapeo de campos](field-mapping.md)
