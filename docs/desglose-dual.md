# Desglose dual de stock

Documentación técnica del refactor **C · Desglose dual de stock** (rama `feat/desglose-dual`).

## ¿Qué resuelve?

El sistema clásico modela stock **por variante** (color × talla × diseño con código de barras propio). Perfecto para prendas donde el cliente pide "5 body león rosados talla 6M".

Pero el cliente Aracely maneja parte de su inventario **agregado**: "de este producto tengo 42 unidades repartidas entre 4 colores, sin saber cuántas de cada uno". Su Excel `INVENTARIO DR REPORTE.xlsx` tiene 134 productos así.

Este refactor agrega el modo **agregado** al sistema sin romper el modo **granular** existente.

---

## Modelo mental

Cada producto tiene un flag `desglose_stock`:

- `true` (default) → **granular**: stock por variantes, comportamiento clásico
- `false` → **agregado**: `stock_directo` INT en la tabla `productos`, sin variantes

Ambos modos coexisten en la misma tabla `inventario_movimientos`:

| variante_id | producto_id | Significado |
|---|---|---|
| SET | SET (backfilleado) | Movimiento **granular** |
| NULL | SET | Movimiento **agregado** |
| NULL | NULL | ❌ Rechazado por CHECK `chk_invmov_sujeto` |

## Invariantes que protege el sistema

1. **Un movimiento debe tener sujeto** — CHECK constraint en 6 tablas (`inventario_movimientos`, `reservas_inventario`, `alertas_stock_config`, `alertas_stock_disparadas`, `traslados_inventario_items`, `tomas_fisicas_items`, `pedidos_cliente_items`).

2. **Un producto agregado no puede tener variantes** — Guardarraíl en `ProductoVariante::saving` que rechaza con `DomainException`.

3. **Un producto no puede cambiar `desglose_stock` si ya tiene movimientos** — Trigger BD `trg_bloqueo_toggle_desglose_stock` + validación Filament (toggle deshabilitado). Cambiar el modo con historia rompería el kardex append-only.

4. **Los reportes deben incluir ambos tipos** — Todas las queries que hacen JOIN a `producto_variantes` se refactoraron para UNION granular+agregado (`ReporteStockBodega`, `InventarioController::stockPorBodega`, `ViewProducto`, `InventarioEnVivo`).

5. **Motor stock polimórfico** — `StockService::saldoFisicoSujeto(Producto|ProductoVariante)` bifurca según tipo. Llamar con `Producto` granular lanza `DomainException` (guardarraíl anti-mal-uso).

---

## Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                    PRODUCTOS                                │
│  desglose_stock=true          desglose_stock=false          │
│  ↓                            ↓                             │
│  variantes[]                  stock_directo (int)           │
│  ↓                            ↓                             │
└──┼────────────────────────────┼─────────────────────────────┘
   │                            │
   ▼                            ▼
┌─────────────────────────────────────────────────────────────┐
│              inventario_movimientos                         │
│  (variante_id, producto_id)   (NULL, producto_id)           │
│                                                             │
│  CHECK: variante_id IS NOT NULL OR producto_id IS NOT NULL │
└──┬──────────────────────────────────────────────────────────┘
   │
   ▼
┌─────────────────────────────────────────────────────────────┐
│              StockService (polimórfico)                     │
│  ────────────────────────────                               │
│  saldoFisico(varianteId, ubi)         ← granular           │
│  saldoFisicoProducto(productoId, ubi) ← agregado           │
│  saldoFisicoSujeto(sujeto, ubi)       ← polimórfico        │
│    · bifurca según instanceof                               │
│    · guardarraíl si sujeto es Producto granular            │
└─────────────────────────────────────────────────────────────┘
```

## Auto-populate crítico

Después del refactor F1, TODAS las Actions viejas (EjecutarTraslado, RecibirMercancia, CerrarTomaFisica, etc.) siguen creando movimientos con solo `variante_id` — no fueron migradas a `producto_id`. Para no dejar el trigger BD como placebo, hay un hook en `InventarioMovimiento::booted::creating`:

```php
static::creating(function (self $mov) {
    if ($mov->producto_id === null && $mov->variante_id !== null) {
        $mov->producto_id = ProductoVariante::whereKey($mov->variante_id)
            ->value('producto_id');
    }
});
```

Esto garantiza que **cualquier movimiento granular** (haya sido creado por Action refactorizada o antigua, seeder, factory, API) lleve su `producto_id`. Sin este hook, el trigger de bloqueo no vería los movs nuevos y la política del toggle sería una ilusión.

---

## Feature flag

`config/features.php`:

```php
'desglose_dual' => env('FEATURE_DESGLOSE_DUAL', false),
```

Actualmente **OFF por defecto**. El sistema funciona igual con el flag apagado — el flag existe como kill-switch para rollback rápido si algo revienta en producción. En el futuro puede usarse para:

- Ocultar el toggle en Filament
- Deshabilitar el importador de formato cliente
- Bloquear la creación de productos agregados por API

---

## Cómo agregar soporte agregado a una Action existente

Patrón repetible (basado en `ReservarStock`):

```php
// 1. Nuevo método polimórfico
public function handleSujeto(
    Producto|ProductoVariante $sujeto,
    // ... resto de params
): SomeResult {
    // 2. Resolver ids del sujeto
    [$varianteId, $productoId] = $sujeto instanceof ProductoVariante
        ? [$sujeto->id, null]
        : [null, $sujeto->id];

    // 3. Usar API polimórfica del motor
    $saldo = $this->stock->saldoDisponibleSujeto($sujeto, $ubicacionId);

    // 4. Al crear registros, incluir producto_id (o dejar que el hook auto-populate lo haga)
    InventarioMovimiento::create([
        'variante_id' => $varianteId,
        'producto_id' => $productoId,
        // ...
    ]);
}

// 5. Método legacy delega en polimórfico (retro-compat)
public function handle(int $varianteId, ...): SomeResult {
    return $this->handleSujeto(ProductoVariante::findOrFail($varianteId), ...);
}
```

---

## Comandos artisan disponibles

```bash
# Smoke tests (12 verificaciones críticas contra BD real, rollback automático)
php artisan desglose-dual:smoke-test

# Cargar Excel del cliente (134 productos agregados)
php artisan inventario:cargar-excel-cliente \
    --archivo="ruta/al/INVENTARIO DR REPORTE.xlsx" \
    --bodega=1 \
    [--dry-run]

# Seed solo las 9 categorías del cliente
php artisan db:seed --class=CategoriasClienteBebesSeeder
```

---

## Limitaciones conocidas (F2 R2 pendiente)

Los siguientes flujos aún NO soportan productos agregados. Si el usuario intenta usarlos con un producto agregado, falla con mensaje claro:

- **EjecutarTraslado** — falla con "El soporte de traslados agregados llega en C-F2 Round 2"
- **PrepararTomaFisica** — no lo incluye en la toma; comentario TODO documenta el gap
- **RecibirMercancia** (Compras) — solo variantes
- **LiquidarImportacion** — solo variantes
- **ProcesarEscaneoEmpaque** — escáner solo lee códigos de variante

Ver [C-F2 R2 en el plan de trabajo](../MEMORY.md) para orden de refactor.

---

## Testing

- **Fast feedback:** `php artisan desglose-dual:smoke-test` (12 tests, ~3 segundos, sobre BD real con rollback)
- **Pest suite:** `tests/Feature/Inventario/DesgloseDualTest.php` (skipea en SQLite, requiere MariaDB para CI)

---

## Migraciones aplicadas

```
2026_09_21_100001_add_desglose_stock_a_productos
2026_09_21_100002_add_producto_id_a_inventario_movimientos
2026_09_21_100003_add_producto_id_a_reservas_inventario
2026_09_21_100004_add_producto_id_a_alertas_stock_config
2026_09_21_100005_add_producto_id_a_traslados_inventario_items
2026_09_21_100006_add_producto_id_a_tomas_fisicas_items
2026_09_21_100007_trigger_bloqueo_toggle_desglose_stock
2026_09_21_100008_add_producto_id_a_alertas_stock_disparadas
2026_09_21_100009_add_producto_id_a_pedidos_cliente_items
```

Rollback: `php artisan migrate:rollback --step=9` (funciona solo si no hay filas con `variante_id=NULL` en tablas afectadas — hay guard en cada `down()`).
