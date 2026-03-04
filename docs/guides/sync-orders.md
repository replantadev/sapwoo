# Sincronización de pedidos

Guía completa para sincronizar pedidos de WooCommerce a SAP Business One.

## Sincronización automática

### Activar sync automático

1. Ve a **SAP Woo Suite → Opciones de Sincronización**
2. Marca **Sincronizar pedidos automáticamente**
3. Selecciona el estado que dispara la sincronización (ej: `processing`)

### ¿Qué ocurre al sincronizar?

1. El pedido WC cambia a estado configurado
2. El plugin crea un **Documento de Ventas** en SAP
3. Se guarda el `DocEntry` en el meta del pedido
4. Se añade nota al pedido: "Sincronizado con SAP - DocEntry: 12345"

## Sincronización manual

### Desde el listado de pedidos

1. Ve a **WooCommerce → Pedidos**
2. Selecciona los pedidos a sincronizar
3. En "Acciones en lote" selecciona **Sincronizar con SAP**
4. Haz clic en **Aplicar**

### Desde un pedido individual

1. Abre el pedido
2. En el metabox de SAP Woo Suite haz clic en **Sincronizar ahora**

## Tipo de documento SAP

Puedes configurar qué tipo de documento crear:

| Tipo | Objeto SAP | Uso recomendado |
|------|-----------|-----------------|
| Pedido de cliente | Orders | Flujo estándar |
| Factura | Invoices | Facturación inmediata |
| Albarán | DeliveryNotes | Solo entrega |
| Pedido reserva | Drafts | Revisión manual |

## Mapeo de estados

Configura qué estados se sincronizan:

| Estado WC | Acción | Documento SAP |
|-----------|--------|---------------|
| processing | Sincronizar | Order |
| completed | Sincronizar | Invoice |
| cancelled | Cancelar en SAP | Cancel |
| refunded | Crear abono | CreditNotes |

## Errores comunes

### "CardCode no encontrado"

El cliente no existe en SAP. Soluciones:
- Verifica el CardCode configurado para la región
- En modo B2B: importa primero el cliente

### "ItemCode no válido"

El producto no existe en SAP:
- Verifica que el SKU coincide con el ItemCode de SAP
- Importa primero los productos

### "Stock insuficiente"

SAP rechaza si no hay stock:
- Verifica disponibilidad en SAP
- Desactiva validación de stock en documentos de venta

## Reintentar sincronización

Si un pedido falló:

1. Corrige el error (ej: crear el cliente)
2. Abre el pedido
3. Haz clic en **Reintentar sincronización**

## Siguiente paso

→ [Importar productos](import-products.md)
