# Mapeo de campos personalizado

El Field Mapper permite configurar qué campos de WooCommerce se sincronizan con qué campos de SAP Business One.

## Acceder al Field Mapper

1. Ve a **SAP Woo Suite → Mapeo de Campos**
2. Selecciona el tipo de entidad a mapear

## Tipos de mapeo disponibles

### Productos (Items)

| Campo WooCommerce | Campo SAP | Tipo |
|-------------------|-----------|------|
| SKU | ItemCode | Texto |
| Nombre | ItemName | Texto |
| Descripción | User_Text | Texto largo |
| Precio regular | Precio lista | Número |
| Stock | OnHand | Número |
| Peso | SWeight1 | Número |

### Pedidos (Orders)

| Campo WooCommerce | Campo SAP | Tipo |
|-------------------|-----------|------|
| Número de pedido | NumAtCard | Texto |
| Fecha | DocDate | Fecha |
| Dirección envío | Address | Texto |
| Método de pago | PaymentMethod | Texto |
| Notas | Comments | Texto |

### Clientes (BusinessPartners)

| Campo WooCommerce | Campo SAP | Tipo |
|-------------------|-----------|------|
| Email | EmailAddress | Email |
| Nombre + Apellido | CardName | Texto |
| NIF/CIF | FederalTaxID | Texto |
| Teléfono | Phone1 | Teléfono |
| Dirección | BillToStreet | Texto |

## Campos UDF (User Defined Fields)

Puedes mapear a campos personalizados de SAP:

1. Asegúrate de que el UDF existe en SAP B1
2. El nombre debe coincidir **exactamente** (ej: `U_ShopifyID`)
3. Respeta el tipo de dato configurado en SAP

### Tipos de UDF soportados

- **Alfanumérico** (`A`): Texto simple
- **Numérico** (`N`): Números enteros o decimales
- **Fecha** (`D`): Formato ISO (YYYY-MM-DD)
- **Memo** (`M`): Texto largo

### Ejemplo de mapeo UDF

```
Meta de producto: _shopify_id → U_ShopifyID (OITM)
Meta de pedido: _canal_venta → U_Canal (ORDR)
```

## Validación de UDFs

Antes de sincronizar, el plugin verifica:

1. El UDF existe en SAP
2. El tipo de dato es compatible
3. El valor no excede la longitud maxima

Si la validación falla, el campo se omite (no bloquea la sincronización).

## Campos calculados

Algunos campos se calculan automáticamente:

- **DocTotal**: Suma de líneas + impuestos
- **CardCode**: Según región de envío (modo B2C)
- **TaxCode**: Según país de destino

## Siguiente paso

→ [Sincronizar pedidos](guides/sync-orders.md)
