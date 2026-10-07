# Contabilidad SIIGO — 1 página para Aracely

**Última actualización:** 2026-10-06 · dueña del flujo: Aracely · soporte: MyTech.

Todo lo contable que sale del ERP debe terminar en SIIGO. Si algo no llega, el
sistema te lo marca. Mirá esta página 2–3 veces por semana para que el libro
contable cuadre al cierre de mes.

---

## 🟢 Bloque 1 · Semáforo diario (30 segundos)

**Entrar a:** `/app/contabilidad/panel`

Arriba del Panel contable hay un recuadro **"Semáforo SIIGO"**:

- 🟢 **Verde (≥ 95%)** · todo bajo control, no toques nada.
- 🟡 **Amarillo (80–94%)** · hay varios asientos sin llegar a SIIGO. Hacé click
  en la tarjeta y pasá al Bloque 2.
- 🔴 **Rojo (< 80%)** · tenés un problema serio. Llamá a MyTech el mismo día.

> El semáforo mira **todos los asientos aprobados**: si el 100% tiene su
> `siigo_journal_id`, Verde. Si falta uno, baja.

---

## 🟡 Bloque 2 · Resolver pendientes (5 minutos)

**Entrar a:** `/app/contabilidad/pendientes-siigo` o `/app/contabilidad/discrepancias-siigo`

Vas a ver 3 tablas:

### 👻 Huérfanos
Asientos aprobados hace más de 24h sin `siigo_journal_id`. Esto significa que
el job quedó colgado o SIIGO rechazó el push.

**Qué hacer:** botón **"Reintentar"** en cada fila. Si vuelve a fallar, abrí la
tabla **"Errores recientes"** más abajo — ahí sale el mensaje de SIIGO (ej:
"cuenta 5195 no existe" → vas a `/app/contabilidad/plan-cuentas` y la agregás).

### ⚖ Delta $
Asientos que **ya llegaron a SIIGO** pero el cuadre interno del ERP no coincide
con lo que SIIGO guardó (suma líneas ≠ cabecera). Esto es raro pero crítico.

**Qué hacer:** botón **"SIIGO"** en la fila → abre el diff ERP ↔ SIIGO.
Comparás los 3 campos (fecha / total / líneas). Si realmente difiere, llamás a
MyTech con el ID del asiento `AM-X` para corregir el payload.

### ❌ Errores recientes (14 días)
Historial de intentos fallidos del log SIIGO. Útil para ver si hay un **patrón**
(ej: todos los pagos a X proveedor fallan → el `tercero_documento` está mal en
Contactos).

---

## 🔎 Bloque 3 · Fuentes de verdad y permisos

| Qué | Dónde | Quién puede |
|---|---|---|
| Ver asientos pendientes | `/app/contabilidad/pendientes-siigo` | Aracely, Gerencia, Contador |
| Reporte de discrepancias | `/app/contabilidad/discrepancias-siigo` | Aracely, Gerencia, Contador |
| Reintentar envío | botón "Reintentar" en cualquiera de las 2 pantallas | Aracely, Gerencia, Contador |
| Ver un journal en SIIGO | botón "SIIGO" en Discrepancias | Aracely, Gerencia, Contador |
| **Exportar CSV del libro** | `/app/contabilidad/reportes/exportar-csv?reporte=mayor` | **Solo Aracely/Gerencia** (CONT-C8) |
| Validación PUC vs SIIGO | `/app/contabilidad/validacion-puc-siigo` | Aracely, Gerencia, Contador |
| Alerta falla permanente | WhatsApp automático a Aracely cuando >=3 fallos del mismo asiento | automático |

---

## 📞 Cuándo llamar a MyTech

- Semáforo rojo que no baja después de reintentar 2 veces.
- Mensaje de error que menciona "token", "credential" o "unauthorized" → se
  venció la conexión con SIIGO, nosotros re-autenticamos.
- Un asiento con **Delta $** que no cuadra ni al reintentar.
- Cualquier mensaje que no entiendas.

**Contacto:** MyTech Solutions · Yeniffer (dev · WhatsApp).

---

### Tareas automáticas que ya corren solas

- Cada 15 min: push de productos nuevos/editados a SIIGO.
- Cada hora: reintento de asientos con backoff `[60s, 5min, 30min, 3h]`.
- Diario (futuro TEST-S7): comparador ERP ↔ SIIGO con reporte por email.
- Permanente: alerta WhatsApp si un asiento falla ≥ 3 veces (CONT-C7).

### No toqueis

- No cambiés el `siigo_journal_id` de un asiento a mano. Si está mal, se corrige
  anulando el asiento en ERP y en SIIGO, y creando uno nuevo.
- No habilites la auto-aprobación de asientos (setting `contabilidad.permitir_auto_aprobar_asiento`).
  La segregación de funciones es por normativa fiscal.
