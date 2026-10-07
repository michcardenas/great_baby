# Auditoría A5 · Flujos Alistador + Despachador (2026-10-06)

## Bugs CRÍTICOS

**B1** · `DashboardController.php:24` redirige Despachador a `app.cola-jorge.index` pero `ColaJorgeController.php:45` solo admite `esAracely() || esAdminBodega() || esAlistador()`. **Despachador recibe 403 al login.**

**B2** · Scope por sede roto para Alistador. `ColaJorgeController.php:64-66,76` usa `when(! empty($misBodegas), ...)`. Como el seeder NO fija `bodegas_asignadas`, `bodegasAsignadasIds()` devuelve `[]` → fail-OPEN: **Alistador BOG ve pedidos CLO/BAQ/MED.** Debe ser fail-closed.

**B3** · `PedidosB2BController.php:38-55` (index) NO filtra por bodega del user. **Despachador BOG ve pedidos de todas las sedes.**

## Bugs ALTOS

**B4** · `ColaJorgeController@imprimir:220` tampoco valida sede. Cualquier alistador imprime hoja de picking de cualquier sede.

**B5** · No existe heartbeat ni liberación de locks para Cola Jorge/B2B (solo Dropi lo tiene). Si Alistador toma pedido y se desconecta, bloqueado hasta reasignación manual admin.

## Bugs MEDIOS

**B6** · No hay endpoint para que Alistador se desasigne. Queda pendiente del admin.

**B7** · `PedidosB2BController@despachar` no actualiza kardex. La salida física queda sin movimiento contable asociado al endpoint — depende de otro job no visible.

**B8** · `DespachoController` e `InventarioOperacionController` mencionados en el prompt no existen en el repo. OK, no son shadow routes.

## Verificaciones correctas (ya estaban bien)

- LOG-J7 gate factura: `PedidosB2BController.php:348` ✓
- LOG-J6 Rack·Sección·Nivel + horas automáticas: `HojaPicking.vue:78,160`, `generado_at = now()` server-side ✓
- Novedad requiere descripción: `ColaJorgeController.php:314` ✓
- Novedad bloquea despacho hasta resolver: `PedidosB2BController.php:358-362` ✓
- Usuarios demo alistador.bog + despachador.bog con roles correctos ✓

## Recomendaciones

1. Agregar `Despachador` al guard de `ColaJorgeController` o crear `BandejaDespachoController` dedicado.
2. Convertir `bodegasAsignadasIds()` en fail-closed en TODOS los controllers de logística.
3. Seedear `setting('inventario.alistador_bodegas.{id}', [bodega_bog_id])` para los demos.
4. Replicar heartbeat/lock del flujo Dropi en Cola Jorge.
5. Enganchar movimiento de kardex dentro de `despachar()` en la misma transacción.
