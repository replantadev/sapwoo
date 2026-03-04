# API REST

SAP Woo Suite expone endpoints REST para integraciones externas.

## Autenticación

Todas las peticiones requieren el header de autenticación:

```
X-SAPWC-Secret: tu-secreto-aqui
```

El secreto se configura en **SAP Woo Suite → Configuración → Secret para webhooks**.

## Endpoints disponibles

### POST /wp-json/sapwc/v1/sync-order

Sincroniza un pedido de WooCommerce a SAP.

**Request:**

```bash
curl -X POST "https://tutienda.com/wp-json/sapwc/v1/sync-order" \
  -H "X-SAPWC-Secret: tu-secreto" \
  -H "Content-Type: application/json" \
  -d '{"order_id": 12345}'
```

**Parámetros:**

| Nombre | Tipo | Requerido | Descripción |
|--------|------|-----------|-------------|
| order_id | integer | Sí | ID del pedido WC |

**Respuesta exitosa (200):**

```json
{
  "success": true,
  "message": "Pedido sincronizado correctamente",
  "doc_entry": 98765
}
```

**Respuesta error (400/500):**

```json
{
  "success": false,
  "message": "El pedido no existe"
}
```

---

### POST /wp-json/sapwc/v1/sync-products

Importa productos desde SAP a WooCommerce.

**Request:**

```bash
curl -X POST "https://tutienda.com/wp-json/sapwc/v1/sync-products" \
  -H "X-SAPWC-Secret: tu-secreto" \
  -H "Content-Type: application/json" \
  -d '{"item_group": "ACCESORIOS", "update_existing": true}'
```

**Parámetros:**

| Nombre | Tipo | Requerido | Descripción |
|--------|------|-----------|-------------|
| item_group | string | No | Filtrar por grupo de artículos |
| update_existing | boolean | No | Actualizar productos existentes (default: true) |

**Respuesta exitosa (200):**

```json
{
  "success": true,
  "message": "Importación completada",
  "created": 15,
  "updated": 42,
  "errors": 2
}
```

---

### POST /wp-json/sapwc/v1/stock-webhook

Recibe actualizaciones de stock desde SAP (webhook).

**Request:**

```bash
curl -X POST "https://tutienda.com/wp-json/sapwc/v1/stock-webhook" \
  -H "X-SAPWC-Secret: tu-secreto" \
  -H "Content-Type: application/json" \
  -d '{"ItemCode": "SKU001", "OnHand": 50, "Warehouse": "01"}'
```

**Parámetros:**

| Nombre | Tipo | Descripción |
|--------|------|-------------|
| ItemCode | string | SKU del producto |
| OnHand | integer | Stock disponible |
| Warehouse | string | Código de almacén |

## Códigos de respuesta

| Código | Significado |
|--------|-------------|
| 200 | Operación exitosa |
| 400 | Parámetros inválidos |
| 401 | No autorizado (secret inválido) |
| 404 | Recurso no encontrado |
| 500 | Error interno |

## Ejemplos de integración

### Zapier / Make

Usa el módulo HTTP para llamar a los endpoints:

1. Configura una petición POST
2. Añade el header `X-SAPWC-Secret`
3. Envía el body en JSON

### Power Automate

```
HTTP Request
├── Method: POST
├── URI: https://tutienda.com/wp-json/sapwc/v1/sync-order
├── Headers:
│   └── X-SAPWC-Secret: @{variables('sapwc_secret')}
└── Body: {"order_id": @{triggerOutputs()?['body/order_id']}}
```

### Node.js

```javascript
const axios = require('axios');

async function syncOrder(orderId) {
  const response = await axios.post(
    'https://tutienda.com/wp-json/sapwc/v1/sync-order',
    { order_id: orderId },
    { headers: { 'X-SAPWC-Secret': process.env.SAPWC_SECRET } }
  );
  return response.data;
}
```

## Rate Limiting

No hay límite estricto, pero se recomienda:
- Máximo 10 peticiones por segundo
- Usar delays entre lotes grandes
- Implementar retry con backoff exponencial

## Siguiente paso

→ [Changelog](../changelog.md)
