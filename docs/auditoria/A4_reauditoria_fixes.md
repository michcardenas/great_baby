# Auditoría A4 · Re-auditoría de los 22 fixes aplicados (2026-10-06)

## Checklist 22 fixes · todos ✓

Verificación completa: todos los 22 fixes del pack 1+2 aplicados correctamente, sin romper rutas ni callers. Idempotency-Key `rec:{id}` compatible con firma `SiigoClient::request`. `DB::afterCommit` funciona fuera de tx (ejecuta inmediato).

## Regresiones encontradas

### 🔴 ALTA
- **`ProductosController.php:129 guardar()`** · El middleware ahora admite Contador (fix #17 Permisos.php:81). `eliminar/duplicar/forzarSync/importarExcelSiigo` sí tienen `esRoot`, pero `guardar()` (POST crear + PUT actualizar) NO → **Contador puede crear/editar productos**. Contradice el comentario de la refactor.

### 🟡 MEDIA
- **`ProductoPayloadBuilder.php:136 paraVariante`** · Asimetría: productos agregados llevan `camposSiigoExtra` + `notes`, variantes granulares NO. Clientes con `desglose_stock=true` siguen perdiendo metadata fiscal.
- **`PagoProveedorController::anular:125`** · Middleware `esContable()` da a Contador/Gerente poder anular pagos sin validación extra · conviene `esRoot`.

### 🟢 BAJA
- Comentarios obsoletos "Cobrador" en `CarteraExtrasController.php:173-174,220` (no afectan ejecución, cae a `default => esContable`).
- `RrhhController.php:29` usa `esAracely() || hasRole('Gerente')` en vez del helper central.
- Comment stale en `AsientosManualesController.php:160`.
- `CarteraExtrasController.php:224` `$scopeCartera = ! esContable` ahora incluye Gerente/Contador como "ven toda la cartera" · verificar que Gerente de ventas no se cuele a cartera completa de otros vendedores.

## Fixes faltantes · 2do pack

1. `ProductosController::guardar()` → `abort_unless(Permisos::esRoot($r->user()) || $r->user()->hasRole('Gerente'), 403)` para honrar lectura-mínima al Contador.
2. `ProductoPayloadBuilder::paraVariante` → aplicar mismo merge `camposSiigoExtra` + `notes` que `paraProducto`.
3. `PagoProveedorController::anular` → agregar `abort_unless(esRoot())` antes del body.
4. Limpiar comentarios obsoletos.

## Verificaciones adicionales OK

- Ninguna ruta apunta al `reintentar()` eliminado.
- `DiscrepanciasSiigo.vue:47` redirige correctamente a `reenviar-siigo` del AsientosManualesController (existe en línea 169).
- `Idempotency-Key = rec:{id}` compatible y seguro con el hash de SiigoClient.
