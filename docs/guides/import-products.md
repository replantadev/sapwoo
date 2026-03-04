# Importar productos desde SAP

Importa tu catálogo de SAP Business One a WooCommerce.

## Acceder al importador

1. Ve a **SAP Woo Suite → Importar Productos**
2. Configura los filtros de importación

## Filtros disponibles

### Por grupo de artículos

Selecciona qué grupos importar:

```
☑ ACCESORIOS
☑ ROPA
☐ SERVICIOS
```

### Por almacén

Filtra productos por almacén disponible:

| Almacén | Descripción |
|---------|-------------|
| 01 | Principal |
| 02 | Outlet |

### Por estado

- **Activos**: Solo items con `frozenFor = "N"`
- **Todos**: Incluye items inactivos

### Por fecha de modificación

Importa solo productos modificados desde una fecha:

```
Desde: 2024-01-01
```

## Opciones de importación

### Actualizar existentes

- **Sí**: Actualiza productos que ya existen (por SKU)
- **No**: Solo crea productos nuevos

### Importar imágenes

- **Ninguna**: Solo datos
- **Imagen principal**: Primera imagen de SAP
- **Todas**: Galería completa

### Estado del producto

- **Borrador**: Revisión manual antes de publicar
- **Publicado**: Visible inmediatamente

## Mapeo de categorías

Configura cómo mapear grupos de SAP a categorías WC:

| Grupo SAP | Categoría WC |
|-----------|-------------|
| ACCESORIOS | Accesorios |
| ROPA_HOMBRE | Ropa > Hombre |
| ROPA_MUJER | Ropa > Mujer |

### Crear categorías automáticamente

Si no existe la categoría en WC, el plugin puede:
- Crearla automáticamente
- Usar una categoría por defecto
- Omitir el producto

## Proceso de importación

1. **Consulta SAP**: Obtiene items según filtros
2. **Paginación**: Procesa en lotes de 100
3. **Validación**: Verifica datos requeridos
4. **Creación/Actualización**: Crea o actualiza en WC
5. **Registro**: Guarda log de la operación

## Progreso en tiempo real

Durante la importación verás:

```
Importando: 150 / 500 productos
Creados: 120
Actualizados: 28
Errores: 2
```

## Importación programada

Configura importación automática:

1. Ve a **Opciones de Sincronización**
2. Activa **Importación programada**
3. Selecciona frecuencia:
   - Cada hora
   - Cada 6 horas
   - Diaria
   - Semanal

## Errores comunes

### "Tiempo de ejecución excedido"

El proceso tarda demasiado:
- Reduce el lote de productos
- Aumenta `max_execution_time` en PHP

### "Memoria insuficiente"

- Aumenta `memory_limit` en PHP
- Procesa menos productos por lote

## Siguiente paso

→ [Importar clientes](import-customers.md)
