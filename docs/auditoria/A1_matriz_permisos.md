# Auditoría A1 · Matriz de permisos por rol (2026-10-06)

## Helpers canónicos (`app/Models/User.php`)

- `esAracely()` / `esRoot()` = {Aracely, Gerencia}
- `esEquipoBodega()` = {Aracely, Gerencia, AdminBodega}
- `esContable()` = {Aracely, Gerencia, Gerente, Contador}
- `esAlistador`/`esAdminBodega`/`esSac`/`esMarketing` = 1-a-1

## Matriz Rol × Módulo (✓ todo · P parcial · ✗ 403)

| Rol \ Módulo | Dash | Vend | Cart | Comp | Inv | Logí | Cont | Dropi | Catál | SIIGO | Mktg | RRHH | Gar |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Aracely | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Gerencia | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Gerente | ✓ | ✗ | P (extras ✓; Facturas/Pagos/CRM 403) | ✗ | ✗ | ✗ | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✓ |
| **Contador** | ✓ | ✗ | **P (NC/ND/Pagos-Prov/Asientos ✓; Dash/Facturas/Pagos 403)** | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Vendedor | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| AdminBodega | ✓ | ✗ | ✗ | P (OC/recepción ✓, index ✗) | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Alistador | ✓ | ✗ | ✗ | P (recepción) | P (Gestión ✓, index ✗) | P (ColaJorge ✓, B2B ✗) | ✗ | P (Operación ✓) | ✗ | ✗ | ✗ | ✗ | ✗ |
| Despachador | ✓ | ✗ | ✗ | ✗ | ✗ | P (B2B ✓, ColaJorge ✗) | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| ServicioCliente | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Marketing | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | P (índice ✓, traslados ✗) | ✗ | ✗ |

## Bugs encontrados · sidebar engaña (muestra) → controller 403

1. **CRÍTICO · Cartera entera** · `AppLayout.vue:123` sidebar incluye Contador+SAC pero `CarteraDashboardController.php:24`, `FacturasController.php:20`, `ContactosController.php:21`, `PagosController.php:19`, `CreditoController.php:21`, `CrmController.php:24` todos exigen `esAracely`. **12 links visibles → 403 al clic.**
2. Compras · `AppLayout.vue:183` Contador visible vs `ComprasController.php:23` `esAracely` + `ComprasGestionController.php:41` `esEquipoBodega` → Contador 403.
3. Bodega · `AppLayout.vue:198` Contador visible vs `InventarioController.php:26` `esEquipoBodega` → Contador 403. Link "Importar inventario cliente" → `ImportarInventarioClienteController.php:27/40` `esAracely` → AdminBodega también 403.
4. Catálogo · `AppLayout.vue:216` Contador visible vs `ProductosController.php:34` `Permisos::puede('productos')` [solo Gerente+Root] y `JerarquiaSiigoController.php:27` idem → Contador 403.
5. Estación Empaque · `AppLayout.vue:89` AdminBodega visible vs `EstacionEmpaqueController.php:33` (Aracely||Alistador) → AdminBodega 403.
6. Servicio · `AppLayout.vue:150` Marketing·parrilla visible a SAC vs `MarketingController.php:25` `esAracely` → SAC 403.
7. **ALTO · Marketing CTA roto** · `AppLayout.vue:165` "Nuevo préstamo" → `/app/inventario/traslados` vs `InventarioGestionController.php:31-35` (Aracely/Alistador/AdminBodega) → **Marketing 403 en su único botón de acción**.
8. Mapa Colombia · `AppLayout.vue:87-88` Gerente vía ROL_SUPER vs `MapaColombiaController.php:23` `esEquipoBodega` → Gerente 403.
9. CosasDelDia · `CosasDelDiaController.php:33` `esEquipoBodega` pero Alistador/Despachador llegan por redirect del Dashboard → inconsistente.
10. **Shadow roles sin seeder** · `CarteraExtrasController.php:32` autoriza rol "Cobrador" y `RrhhController.php:27` autoriza "RRHH" — NO están en los 10 roles declarados → gates muertos.
11. Divergencia · `AsientosManualesController.php:154` reimplementa `hasRole('Aracely'|'Gerencia'|'Gerente')` en vez de usar `esContable()`.
12. Dentro de Cartera · `CarteraDashboard/Facturas/Pagos/CRM` usan `esAracely` mientras `CarteraExtras` usa `esContable` → gates inconsistentes **en el mismo módulo**.
13. `SiigoController.php:36-37` `esAracely() || hasAnyRole(['Gerente','Gerencia'])` (verbose) vs línea 374 y 600 `esAracely/esRoot` → inconsistencia interna.

## Recomendaciones top 3

1. **Unificar Cartera a `esContable()`** (o crear `esCartera()` con SAC para Contactos/CRM). Hoy sidebar y controller hablan idiomas distintos — mayor fuente de 403s legítimos.
2. **Arreglar CTA de Marketing** · ruta dedicada `MarketingPrestamos@crearTraslado` con gate Marketing+Aracely, o quitar el link del sidebar. El rol Marketing hoy es decorativo porque su único botón cae en 403.
3. **Fuente única de verdad para el sidebar** · hacer que `AppLayout.vue` consuma `Permisos::seccionesDe($u)` vía Inertia share, en lugar de hard-codear `roles:[]` por grupo. Elimina las 7 divergencias detectadas y centraliza en `Permisos.php`.
