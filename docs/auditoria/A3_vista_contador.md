# Auditoría A3 · Vista del rol Contador E2E (2026-10-06)

## Pantallas verificadas

| Pantalla | Controller | Guard | Estado |
|---|---|---|---|
| /app/cartera | CarteraDashboardController | **esAracely** | 🔴 Contador 403 (sidebar lo muestra) |
| /app/facturas | FacturasController | **esAracely** | 🔴 Contador 403 |
| /app/pagos | PagosController | **esAracely** | 🔴 Contador 403 |
| /app/contactos | ContactosController | **esAracely** | 🔴 Contador 403 |
| /app/credito | CreditoController | **esAracely** | 🔴 Contador 403 |
| /app/cartera/cobranzas,movimientos,solicitudes | CarteraExtrasController | esAracely+Gerente+Contador+Cobrador | OK |
| /app/cartera/retenciones,NC,ND,pagos-proveedor | *Controller | esContable | OK |
| /app/contabilidad (dashboard) | ContabilidadController | esContable | OK |
| /app/contabilidad/panel (CONT-C6) | ContabilidadExtrasController::panel | esContable | OK |
| /app/contabilidad/plan-cuentas (importar/eliminar) | PlanCuentasController | esContable | ⚠ PUC exfiltrable |
| /app/contabilidad/reportes | ContabilidadExtras::reportes | esContable | ⚠ badge "Excel" sin botón real |
| /app/contabilidad/reportes/exportar-csv (CONT-C8) | ContabilidadExtras::exportarCsv | esContable+esRoot | OK (doble gate) |
| /app/contabilidad/pendientes-siigo (CONT-C1) | ContabilidadPendientesSiigoController | **ninguno** | 🚨 CRÍTICO |
| /app/contabilidad/discrepancias-siigo (CONT-C2) | ContabilidadDiscrepanciasController | esContable | OK |
| /app/contabilidad/validacion-puc-siigo (CONT-C5) | ValidacionPucSiigoController | **ninguno** | 🚨 CRÍTICO · + no está en sidebar |
| /app/contabilidad/asientos-manuales | AsientosManualesController | esContable | OK |

## Bugs encontrados

### 🚨 CRÍTICO seguridad
1. **`ContabilidadPendientesSiigoController.php:26`** · clase NO implementa `HasMiddleware`; ruta solo tiene `throttle`. Cualquier user autenticado (Vendedor, Alistador, SAC, Marketing) ve recepciones, pagos, NC/ND, asientos y mensajes de error SIIGO.
2. **`ValidacionPucSiigoController.php:26`** · mismo patrón: sin middleware. Expone PUC vivo + settings SIIGO críticos a cualquiera.

### 🔴 ALTA
3. **Guards Cartera desalineados con sidebar** · `AppLayout.vue:123` roles=[...ROL_SUPER,'Contador','ServicioCliente'] vs controllers con `esAracely`:
   - `FacturasController.php:20`
   - `CarteraDashboardController.php:24`
   - `PagosController.php:19`
   - `CreditoController.php:21`
   - `ContactosController.php:21`

### 🟡 MEDIO
4. **PUC exfiltrable/mutable por Contador** · `PlanCuentasController.php:115,122` · `eliminar()` e `importar()` (reemplazo masivo CSV/XLSX) solo piden `esContable()`. Debería ser `esRoot` como CONT-C8.
5. **Export badge sin endpoint** · `Reportes.vue:69` muestra "Excel"/"PDF+Excel" sin botón real que llame a `/contabilidad/reportes/exportar-csv`.
6. **Código muerto** · `ContabilidadDiscrepanciasController.php:227` · `reintentar()` no mapeado; el Vue redirige al controller de asientos. Confunde.
7. **Links de sidebar incompletos** · `AppLayout.vue:229-237` · falta `/app/contabilidad/validacion-puc-siigo` (CONT-C5); `Asientos manuales` está en grupo Cartera (línea 142) y no en Contabilidad.

## Recomendaciones

1. **Unificar guards con la matriz** · reemplazar `esAracely()` por `esContable()` en los 5 controllers de Cartera listados.
2. **Agregar `HasMiddleware` con `esContable()`** a `ContabilidadPendientesSiigoController` y `ValidacionPucSiigoController`.
3. **Promover a `esRoot`** las acciones destructivas del PUC (`eliminar` + `importar`).
4. **UX Reportes** · agregar botones "Exportar CSV" cuando `user.es_root`, o quitar los badges "Excel"/"PDF+Excel" para no prometer.
5. **Completar nav Contabilidad** · añadir "Validación PUC SIIGO" al sidebar, mover "Asientos manuales" de Cartera a Contabilidad, eliminar método muerto.
