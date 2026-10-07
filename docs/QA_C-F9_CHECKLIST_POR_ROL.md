# C-F9 · QA manual · 5 días por rol

**Objetivo:** validar que los 7 roles del ERP funcionan de punta a punta antes
del deploy a producción. Si un paso falla, se abre **ticket root** (no síntoma)
y se congela el deploy hasta cerrarlo.

**Duración:** 5 días hábiles · 1 rol por día + 1 día de regresión.
**Dueña:** Aracely + MyTech (Yeniffer).
**Entorno:** `http://127.0.0.1:8090` (local) o staging Hostinger (según fase).

## Convenciones

- `[ ]` sin tocar · `[x]` OK · `[!]` falla (abrí ticket) · `[-]` no aplica.
- Cada ítem debe acabar en **captura o observación de 1 línea**.
- Si un ítem tiene preconditions, se anotan con `pre:`.
- Si rompe, se registra en `/qa/C-F9-tickets.md` con: rol, pantalla, URL,
  pasos para reproducir, impacto (bloqueante/alto/medio/bajo).

---

## Día 1 · Aracely (super admin)

### A1 · Login + Dashboard
- [ ] `/app/login` acepta `aracely@greatbaby.com` / `demo`.
- [ ] `/app` muestra todos los grupos del sidebar.
- [ ] Ⓚ+K abre búsqueda global y encuentra producto por SKU y cliente por NIT.

### A2 · Catálogo y SIIGO
- [ ] `/app/productos` lista con paginación 25 y filtro línea/activo.
- [ ] Crear producto nuevo → verificar badge "Sin SIIGO" y botón **Push manual**.
- [ ] `/app/siigo` muestra KPIs del sync y kill-switch.
- [ ] Botón "Traer mis cambios de SIIGO" trae productos del sandbox.

### A3 · Contabilidad SIIGO
- [ ] `/app/contabilidad/panel` muestra **Semáforo SIIGO** con % correcto.
- [ ] `/app/contabilidad/discrepancias-siigo` renderiza las 3 tablas.
- [ ] Click en "SIIGO" de un asiento con `siigo_journal_id` → modal con diff.
- [ ] Botón "Reintentar" en un huérfano → flash "re-encolado".
- [ ] `/app/contabilidad/reportes/exportar-csv?reporte=mayor` descarga CSV.

### A4 · Reglas de negocio
- [ ] `/app/reglas` guarda cambio (ej: timeout retorno) y persiste al refrescar.
- [ ] Toggle `contabilidad.permitir_auto_aprobar_asiento` cambia el gate real.

---

## Día 2 · Vendedor (Carlos Vendedor Demo)

### V1 · Sidebar restringido (Miracle style)
- [ ] Login como `vendedor.demo@greatbaby.com` → sidebar **solo** muestra:
    `Mi gestión comercial` (Mi Panel · Ventas por Cliente · Contado/Crédito · Seguimiento).
- [ ] NO ve Cartera, Compras, Dropi, Contabilidad, Catálogo, SIIGO, RRHH.

### V2 · Panel + KPIs
- [ ] `/app/vendedor` muestra 3 KPIs (ventas facturadas / pedidos / ticket).
- [ ] Chips de período (Hoy / Semana / Mes / Año) cambian los números.
- [ ] Sparkline 30 días se dibuja.

### V3 · Armar pedido en terreno (LOG-J1)
- [ ] Buscar cliente por razón social → lista aparece.
- [ ] Si cliente tiene **mora crítica**, semáforo rojo aparece en NuevoPedido.
- [ ] Agregar 2 ítems, confirmar → pedido queda en `enviado`.
- [ ] Verificar que `vendedor_id = Carlos` en el pedido creado.
- [ ] Pre: cliente con mora → el pedido queda **retenido** (LOG-J2), no `enviado`.

### V4 · Vistas Miracle
- [ ] `/app/vendedor/ventas-por-cliente` muestra ranking con 🏆 en #1.
- [ ] `/app/vendedor/contado-credito` muestra dona SVG + 3 barras.
- [ ] `/app/vendedor/seguimiento` muestra las 3 tablas (sin facturar · por cobrar · últimos).

---

## Día 3 · Gerencia + Contador

### G1 · Autorizaciones (LOG-J3)
- [ ] Login como Gerencia → `/app/pedidos-b2b?estado=retenido` muestra pedido retenido del día 2.
- [ ] Botón "Autorizar" pide **motivo visible** (no vacío).
- [ ] Al autorizar, pedido pasa a `aprobado` y vendedor recibe notificación.
- [ ] Al rechazar, pedido pasa a `rechazado` con el motivo.

### C1 · Cartera
- [ ] Login como Contador → ve Cartera completa, NO ve Compras/Dropi.
- [ ] `/app/facturas` lista con paginación y filtros estado/vencimiento.
- [ ] Registrar pago manual → factura queda `pagada` o `parcial` según monto.
- [ ] Importar extracto bancario (Excel) → matcheo automático funciona.

### C2 · Contabilidad SIIGO (del día)
- [ ] `/app/contabilidad/pendientes-siigo` muestra pendientes del día.
- [ ] Crear asiento manual → cuadra débito=crédito antes de guardar.
- [ ] Aprobar asiento (requiere Gerencia si no fuiste tú quien lo creó).
- [ ] Verificar que entra al `siigo_queue` y en <2 min tiene `siigo_journal_id`.

---

## Día 4 · AdminBodega + Alistador + Despachador

### B1 · Login AdminBodega (Jorge · BOG)
- [ ] Login → sidebar muestra solo **Logística** + **Inventario** + **Despachos**.
- [ ] NO ve Compras ni card "Dinero Dropi sin cobrar".
- [ ] NO ve pedidos de otras bodegas (CLO/BAQ/MED).

### B2 · Cola de alistamiento (LOG-J5)
- [ ] `/app/logistica/cola-jorge` muestra 4 columnas: Sin asignar · En picking · Alistado · **Con novedad**.
- [ ] Arrastrar pedido de "Sin asignar" a un alistador → cambia de columna.
- [ ] Marcar pedido "Con novedad" → NO avanza a Alistado (bloquea despacho).

### A1 · Alistador en celular
- [ ] Login como `alistador.bog@greatbaby.com` en resolución móvil (375px).
- [ ] Ver solo sus pedidos asignados.
- [ ] Imprimir hoja de picking (LOG-J6) → muestra **Rack·Sección·Nivel** y
      hora de inicio automática del sistema (NO editable manualmente).
- [ ] Escáner cámara abre cuando se da click en "Escanear producto".
- [ ] Marcar pedido como "Alistado" → aparece en columna del Despachador.

### D1 · Despachador (LOG-J7)
- [ ] Login como Despachador → ve pedidos alistados pendientes de despacho.
- [ ] **Gate duro:** no permite despachar sin factura/remisión descargada.
- [ ] Al despachar, pedido pasa a `despachado` + se actualiza kardex.

### R1 · Recepción (LOG-J8)
- [ ] Recepción de OC → selector `apto / avería / cuarentena / revisión` por línea.
- [ ] Si marco "avería" → va a ubicación `AVERIA-BOG` automática.
- [ ] Si marco "cuarentena" → va a `CUARENTENA-BOG`.

---

## Día 5 · Marketing + Servicio al Cliente + Regresión

### M1 · Marketing (LOG-J9)
- [ ] Login `marketing.demo@greatbaby.com` → ve solo "Marketing · Préstamos".
- [ ] NO ve precios, stock comercial ni cartera.
- [ ] `/app/marketing` muestra sus préstamos abiertos.
- [ ] Crear traslado de préstamo bodega → MKT-BOG → aprobado por AdminBodega.

### S1 · Servicio al Cliente
- [ ] `/app/garantias` lista tickets con filtro estado.
- [ ] Crear ticket nuevo → asigna número, pide RMA, foto obligatoria.
- [ ] Cambiar estado → notif WhatsApp al cliente (si Habeas Data opt-in).

### R2 · Regresión
- [ ] Login/logout de los 7 roles sin errores.
- [ ] `/app/notificaciones` para cada rol muestra solo lo pertinente.
- [ ] `php artisan siigo:smoke-test` corre OK contra sandbox.
- [ ] `php artisan siigo:comparador-diario --dia=hoy` genera reporte sin crash.
- [ ] `php artisan test` → 100% verde (o explicar cualquier skip).
- [ ] Lighthouse móvil en `/app` ≥ 70 performance, 100 accesibilidad.

---

## Criterio de deploy (go/no-go para C-F10)

- Zero tickets **bloqueantes** abiertos.
- ≤ 3 tickets **altos** con workaround documentado.
- CSV export restringido a esRoot verificado.
- Semáforo SIIGO en Verde (≥95%) con datos reales.

Si cualquiera falla → NO deploy, se repite C-F9 tras los fixes.
