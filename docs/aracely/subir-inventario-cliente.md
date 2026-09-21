# Cómo subir el inventario del cliente (Aracely)

Manual paso a paso para cargar el archivo Excel de inventario del cliente al sistema.

## 📁 Formato del archivo esperado

El archivo debe ser un `.xlsx` con la hoja **`Hoja1`** que contenga estas columnas:

| Referencia | Descripcion | Existencia | Variación |
|---|---|---|---|
| 202510-5 | 202510-5 LAVA TETERO | 2 | VERDE/CELESTE/ROSADO |
| 23-73 | 23-73 TETERO PEQUEÑO | 2 | ROSADO/CELESTE |

Filas de **categoría** — con solo la columna A completa (ej: `"ALIMENTACION"`) — separan bloques.

## 🚀 Cómo cargarlo

### Opción 1 — Por comando (recomendada, el equipo técnico corre esto)

```bash
# Ver primero qué pasaría (dry-run, no toca BD)
php artisan inventario:cargar-excel-cliente \
    --archivo="/ruta/al/INVENTARIO DR REPORTE.xlsx" \
    --bodega=1 \
    --dry-run

# Cargar de verdad
php artisan inventario:cargar-excel-cliente \
    --archivo="/ruta/al/INVENTARIO DR REPORTE.xlsx" \
    --bodega=1
```

- `--bodega=1` → ID de la bodega donde se registra el stock inicial. Ver bodegas disponibles: `mysql greatbaby -e "SELECT id, nombre FROM inventario_ubicaciones WHERE activa=1"`
- El comando es **idempotente** — puedes correrlo múltiples veces sin duplicar. Si un producto ya existe, actualiza sus datos pero NO duplica los movimientos de kardex.

### Opción 2 — Desde el panel Filament (para futuro, cuando esté la UI)

Por ahora se hace por comando. La UI llegará junto con el rediseño del importador de productos.

---

## ✅ Qué esperar

Al terminar, verás una tabla resumen:

```
+----------------------------+-------+
| métrica                    | valor |
+----------------------------+-------+
| Productos creados          | 134   |
| Productos actualizados     | 0     |
| Movs de kardex insertados  | 134   |
| Filas ignoradas (vacías)   | 2     |
| Filas sin categoría (skip) | 0     |
+----------------------------+-------+
```

- **Productos creados** — nuevos productos en modo agregado
- **Productos actualizados** — ya existían con este mismo ref
- **Movs de kardex insertados** — cargas iniciales de stock
- **Filas ignoradas** — filas basura del pie del Excel (totales, fecha)

## ⚠️ Casos especiales

### El comando dice "Skip: 'XYZ' ya existe como GRANULAR"

Significa que hay un producto con la misma referencia pero es **granular** (con variantes) en el sistema. El importador NO sobreescribe estos porque cambiar el modo rompería el kardex.

**Opciones:**
1. Renombrar la referencia en el Excel (ej: agregar `-AGG` al final)
2. Eliminar el producto granular en el admin si no tiene movimientos y volver a correr
3. Dejarlo así — el resto sí se importa, solo ese ref se salta

### Puedo modificar un producto agregado después de importar?

**Sí**, pero con limitaciones:
- ✅ Editar nombre, categoría, descripción, precio → OK
- ✅ Cambiar `stock_directo` desde Filament → se registra movimiento
- ❌ Activar "Desglosar stock por variante" si ya tiene movimientos → bloqueado por trigger BD

### Se puede volver a ejecutar si el cliente manda una versión actualizada?

**Sí**, es idempotente:
- Productos que ya existen se actualizan (nombre, categoría, descripción)
- `stock_directo` se sobreescribe con el nuevo valor del Excel
- Los movimientos históricos de kardex NO se tocan (solo se inserta el marker de F7 la primera vez)

**Ojo:** si Aracely editó el nombre de un producto en Filament entre cargas, la recarga lo va a pisar con el nombre del Excel. Documentar esto con el equipo.

---

## 🔍 Cómo verificar la carga

En el panel Filament:

1. Ve a `/admin/productos`
2. En el filtro "Modo" arriba a la derecha, elige **"Solo agregados (stock único)"**
3. Deberías ver los 134 productos con badge naranja 🟠 "Agregado"

Para ver el stock inicial:
1. Ve a **Stock por bodega** en el menú lateral
2. Filtra por la bodega donde cargaste
3. Los agregados aparecen con marca "agregado" en la columna modo

---

## 🆘 Si algo sale mal

El comando corre TODO dentro de una transacción. Si algo falla a mitad, **nada se guarda** — la BD queda igual que antes.

Los errores comunes:
- `--archivo no existe` → verificar la ruta
- `--bodega debe ser un ID válido` → verificar con `mysql greatbaby -e "SELECT id, nombre FROM inventario_ubicaciones"`
- `Column cannot be null` → el Excel tiene un formato diferente al esperado, revisar headers

**Para revertir toda una carga:** contactar al equipo técnico. La operación segura es identificar los productos por su `stock_inicial_form` en `inventario_movimientos.tipo` y eliminarlos junto con el producto.
