# Importar clientes desde SAP

Sincroniza tus clientes de SAP Business One con usuarios de WordPress/WooCommerce.

## Cuándo usar esta función

- Tiendas **B2B** donde cada cliente tiene su CardCode
- Migración inicial desde SAP a WooCommerce
- Sincronización periódica de nuevos clientes

## Acceder al importador

1. Ve a **SAP Woo Suite → Importar Clientes**
2. Configura los filtros

## Filtros de importación

### Por CardCode

Filtra por prefijo de CardCode:

| Prefijo | Descripción |
|---------|-------------|
| `C` | Clientes nacionales |
| `E` | Clientes exportación |
| `W` | Clientes web |

### Por grupo

Selecciona grupos de clientes SAP:

```
☑ Minoristas
☑ Mayoristas
☐ Proveedores
```

### Por estado

- **Activos**: Solo clientes activos
- **Todos**: Incluye inactivos

## Datos importados

| Campo SAP | Campo WordPress |
|-----------|-----------------|
| CardCode | Meta: `_sap_card_code` |
| CardName | Display name |
| EmailAddress | Email (y username) |
| Phone1 | Billing phone |
| FederalTaxID | Meta: `_nif` |
| Address | Billing address |
| Currency | Meta: `_currency` |

## Opciones de importación

### Si el email ya existe

- **Actualizar**: Vincula el usuario existente con el CardCode
- **Omitir**: No importa ese cliente
- **Crear nuevo**: Crea con email modificado (añade sufijo)

### Rol de WordPress

Asigna rol a los usuarios importados:
- Customer (cliente WC)
- Wholesale Customer (si usas plugin de mayoristas)
- Subscriber

### Notificar al usuario

- **Sí**: Envía email con credenciales
- **No**: Crea usuario sin notificar

## Proceso de importación

1. Consulta BusinessPartners en SAP
2. Filtra por tipo `cCustomer`
3. Valida email único
4. Crea usuario WordPress
5. Asigna meta `_sap_card_code`
6. Guarda direcciones de facturación/envío

## Importación incremental

Para sincronizar solo clientes nuevos:

1. Marca **Solo nuevos desde última importación**
2. El plugin guarda la fecha de última importación
3. Consulta clientes creados después de esa fecha

## Verificar importación

Después de importar:

1. Ve a **Usuarios → Todos los usuarios**
2. Filtra por rol "Customer"
3. Verifica que el meta `_sap_card_code` esté asignado

## Errores comunes

### "Email duplicado"

El cliente SAP tiene el mismo email que otro usuario WP:
- Actualiza el email en SAP
- O usa la opción "Vincular existente"

### "CardCode ya asignado"

Otro usuario ya tiene ese CardCode:
- Verifica duplicados en WP
- Un CardCode = Un usuario

## Siguiente paso

→ [API REST](rest-api.md)
