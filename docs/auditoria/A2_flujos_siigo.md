# Auditoría A2 · Flujos ERP ↔ SIIGO (2026-10-06)

## Mapa de los 6 flujos

| # | Flujo | Observer/Trigger | Job | Idempotencia | Estado |
|---|---|---|---|---|---|
| 1 | Producto | `ProductoObserver` (AppServiceProvider:34) | `PushProductoASiigo` | `siigo_id` + debounce; sin Idempotency-Key (SIIGO no lo soporta en productos) | ✓ wired |
| 2 | Factura venta B2B | **No observer** · dispatch desde `PedidosB2BController@facturar:510` | `ReintentarEmisionDian` → `SiigoEmisionService::emitir` | `Idempotency-Key=inv:{id}` + `emitiendo_at` lock 60s + guard CUFE | ✓ wired (⚠ sin `siigo_sync_log`) |
| 3 | Nota crédito manual | `NotaCreditoObserver` (AppServiceProvider:30) | `PushNotaCreditoASiigo` | `Idempotency-Key=nc:{id}` + guard `siigo_id` | ✓ wired |
| 4 | Asiento manual | **No observer** · dispatch desde `AsientosManualesController@aprobar:160` | `PushAsientoManualASiigo` | `Idempotency-Key=asman:{id}` + `lockForUpdate` | ⚠ gap · B2 |
| 5 | Pago proveedor | `PagoProveedorObserver` (AppServiceProvider:43) | `PushPagoProveedorASiigo` | `Idempotency-Key=pprov:{id}` | ✓ wired |
| 6 | Recepción compra | `RecepcionCompraObserver` (AppServiceProvider:26) | `PushRecepcionASiigo` → `emitirCompra` | **Sin Idempotency-Key en path individual (línea 412)**; sí en consolidado (509) | ⚠ gap · B1 |

Todos los Jobs: `tries=5`, backoff con jitter `[10,30,60,120,300]`, `RateLimited('siigo-api')`, `WithoutOverlapping` por entidad, log en `siigo_sync_log` éxito/omisión/fallo (excepto `ReintentarEmisionDian`).

## Bugs priorizados

### 🔴 CRÍTICO
- **B1** `SiigoEmisionService.php:412` · `emitirCompra` llama `request('POST','/v1/purchases',$payload)` **sin** 5º arg `idempotencyKey`. Con `tries=5` del job, cualquier timeout/5xx tras POST exitoso → **FC duplicada en SIIGO**. El path consolidado (línea 509) sí lo pasa; inconsistente.
- **B2** `AsientosManualesController.php:159-160` · `$asiento->update(['estado'=>'aprobado']); PushAsientoManualASiigo::dispatch(...)` sin `DB::afterCommit`. Si corre dentro de transacción externa que rollbackea, el job queda **huérfano** y pushea asiento con estado inconsistente.

### 🟠 ALTO
- `PushPagoProveedorASiigo.php:61-74` y `PushAsientoManualASiigo.php:62-76` · envuelven HTTP SIIGO en `DB::transaction + lockForUpdate`. Mantener fila bloqueada 30s bloquea lectores + si falla `forceFill`/save tras SIIGO 201 → rollback de `siigo_voucher_id`/`siigo_journal_id` → divergencia (SIIGO tiene doc, ERP no).
- `ReintentarEmisionDian.php` · no registra en `siigo_sync_log` ni éxito ni fallo, solo `NotificacionErp`. **El panel CONT-C2 no ve el flujo factura B2B** · monitoreo ciego.
- `SiigoEmisionService::emitir:65,80` · en error de red/4xx agenda `ReintentarEmisionDian` pero no limpia debounce Cache ni chequea kill-switch SIIGO → si Aracely baja `push_auto`, el reintento igual postea.

### 🟡 MEDIO
- `PushProductoASiigo.php:339-348` · `failed()` auto-restaura producto soft-deleted tras agotar tries. Puede sorprender + dispara `restored()` → **loop potencial** push→fail→restore→push.
- `PushProductoASiigo.php:197-305` · no persiste `estado='exitoso'` en `siigo_sync_log` desde el Job (depende de Actions). Asimetría de observabilidad vs otros Push jobs.
- `SiigoEmisionService::emitirCompra:338-346` · detección de "hermanas" sólo mira `factura_proveedor` igualito; `FC-001` vs `001` falla silenciosamente → emite N FC separadas.
- `ProductoPayloadBuilder::camposSiigoExtra:183` · método privado **nunca invocado** desde `paraProducto`/`paraVariante`. Toda la metadata jerarquía/NIIF se arma pero no viaja a SIIGO.
- `SiigoEmisionService::emitirVoucher:574` · `due.consecutive => (int)$p->factura->consecutivo ?: 0` manda `0` cuando no hay consecutivo → egreso suelto sin cruzar con factura.

## Riesgos de datos y mitigación

1. **Duplicación FC proveedor** (crítico). Mitigación: pasar `"rec:{$r->id}"` como 5º arg en línea 412.
2. **Divergencia ERP↔SIIGO en voucher/asiento** (alto). Mitigación: sacar HTTP de transacción, `lockForUpdate` solo para re-check guard + `forceFill` + commit; Idempotency-Key cubre retry.
3. **Jobs huérfanos de asiento aprobado** (alto). Mitigación: envolver dispatch en `DB::afterCommit`.
4. **Factura B2B sin trazabilidad** (medio-alto). Mitigación: añadir inserts en `handle()`/`failed()` de `ReintentarEmisionDian` con `recurso='facturas_venta'` para que CONT-C2/C3 las vea.
5. **Pérdida silenciosa de metadata fiscal** (medio). Mitigación: incluir retorno de `camposSiigoExtra` en el `array_merge` de `paraProducto`/`paraVariante`.
6. **Loop restore→push** (medio). Mitigación: flag en `restore()` para suprimir push, o loggear sin dispatch.
7. **Pago cliente/egreso no cruza FC** (bajo-medio). Mitigación: validar consecutivo > 0 antes de armar `due`.

Flujos sin bug observable: Producto (1) y NC manual (3) — correctos end-to-end.
