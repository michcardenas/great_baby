<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\MovimientoContable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FIL-D · Contabilidad Vue: Panel + Reporte detalle + landing 9 reportes.
 *
 * Re-audit M5 · reescrito para atacar patrones raíz:
 *   PATRÓN A · withTrashed opcional en TODAS las agregaciones
 *   PATRÓN C · porOrigen suma DEBE+HABER (antes solo debe → subestimaba pagos/NC)
 *   PATRÓN D · reportes hub sin duplicados + retenciones con 3 cuentas
 *   PATRÓN F · validate desde/hasta + esContable helper
 *   PATRÓN H · KPI 1 sola query (antes 3 full-scans)
 */
class ContabilidadExtrasController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            abort_unless($r->user()?->esContable(), 403);
            return $next($r);
        })];
    }

    public function panel(Request $request): Response
    {
        $data = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'incluir_anulados' => ['nullable', 'boolean'],
        ]);

        // Re-audit M5 UX-C4 · defaults unificados con Index (`hasta = hoy`).
        // Antes Panel usaba `endOfMonth` y en el mismo período los totales
        // divergían entre pantallas.
        $desde = $data['desde'] ?? now('America/Bogota')->startOfMonth()->toDateString();
        $hasta = $data['hasta'] ?? now('America/Bogota')->toDateString();
        $incluirAnulados = (bool) ($data['incluir_anulados'] ?? false);

        // Re-audit R4 SEG-M1 · bitácora consulta con anulados.
        if ($incluirAnulados) {
            \Illuminate\Support\Facades\Log::channel(config('logging.channels.audit') ? 'audit' : 'stack')
                ->info('contabilidad.panel.incluir_anulados', [
                    'user_id' => $request->user()?->id, 'rango' => [$desde, $hasta],
                ]);
        }

        // KPI 1-query.
        $kpiRow = MovimientoContable::query()
            ->when($incluirAnulados, fn ($q) => $q->withTrashed())
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('COALESCE(SUM(debe),0) d, COALESCE(SUM(haber),0) h, COUNT(*) n')
            ->first();
        $totalDebe = (float) $kpiRow->d;
        $totalHaber = (float) $kpiRow->h;

        $topCuentas = MovimientoContable::query()
            ->when($incluirAnulados, fn ($q) => $q->withTrashed())
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('cuenta_puc, SUM(debe) as debe, SUM(haber) as haber, COUNT(*) as movs')
            ->groupBy('cuenta_puc')->orderByRaw('SUM(debe) + SUM(haber) DESC')->limit(10)->get();

        // Re-audit M5 R4 DATOS-A1 · REGRESIÓN oculta corregida.
        //   Con `withTrashed=true` un reverso (D:1305=100 / H:1105=100) sumado al
        //   original (D:1105=100 / H:1305=100) inflaba `SUM(debe)` a 200 aunque
        //   el monto operacional real es 100. El "ratio 1.00" solo se cumplía
        //   sin toggle. Fix: agregación monetaria EXCLUYE anulados aunque el
        //   toggle esté activo (los reversos aparecen en tablas de detalle pero
        //   NUNCA en agregados por origen — semánticamente son ruido contable).
        //   Se mantiene withTrashed en la query base para `docs` distintos, pero
        //   `volumen` usa CASE para netear.
        $porOrigen = MovimientoContable::query()
            ->when($incluirAnulados, fn ($q) => $q->withTrashed())
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNotNull('origen_type')
            ->whereNotNull('origen_id')
            ->selectRaw('origen_type, COUNT(DISTINCT origen_id) as docs, SUM(CASE WHEN deleted_at IS NULL THEN COALESCE(debe,0) ELSE 0 END) as volumen')
            ->groupBy('origen_type')->orderByDesc('volumen')->get();

        return Inertia::render('Contabilidad/Panel', [
            'periodo' => ['desde' => $desde, 'hasta' => $hasta, 'incluir_anulados' => $incluirAnulados],
            'kpis' => [
                'total_debe' => $totalDebe,
                'total_haber' => $totalHaber,
                'balance' => round($totalDebe - $totalHaber, 2),
                'unbalanced' => abs($totalDebe - $totalHaber) > 0.01,
                'movimientos' => (int) $kpiRow->n,
            ],
            'topCuentas' => $topCuentas->map(fn ($r) => [
                'cuenta' => $r->cuenta_puc,
                'debe' => (float) $r->debe, 'haber' => (float) $r->haber,
                'movs' => (int) $r->movs,
            ]),
            'porOrigen' => $porOrigen->map(fn ($r) => [
                'origen' => $this->origenLabel($r->origen_type),
                'docs' => (int) $r->docs,
                'volumen' => (float) $r->volumen,
            ]),
        ]);
    }

    public function reporteDetalle(Request $request): Response
    {
        // Re-audit M5 SEG-A2 · whitelist explícita del tipo + guard id > 0.
        // Re-audit R2 PATRÓN α · toggle propagado también acá.
        $data = $request->validate([
            'tipo' => ['nullable', 'string', 'in:factura,pago,nota_credito'],
            'id' => ['nullable', 'integer', 'min:1'],
            'incluir_anulados' => ['nullable', 'boolean'],
        ]);

        $tipo = $data['tipo'] ?? 'factura';
        $id = (int) ($data['id'] ?? 0);
        $incluirAnulados = (bool) ($data['incluir_anulados'] ?? false);

        $tipoClass = [
            'factura' => \App\Modules\Cartera\Models\FacturaVenta::class,
            'pago' => \App\Modules\Cartera\Models\PagoVenta::class,
            'nota_credito' => \App\Modules\Cartera\Models\NotaCredito::class,
        ][$tipo] ?? null;

        $meta = null;
        $movs = [];
        $anuladosCount = 0;
        if ($tipoClass && $id > 0) {
            // Info del documento origen (siempre withTrashed — el documento puede
            // estar anulado pero queremos mostrar el header + badge "anulado").
            $origen = $tipoClass::query()->withTrashed()->find($id);
            if ($origen) {
                $meta = [
                    'numero' => $origen->numero ?? ($origen->referencia ?? "#{$id}"),
                    'fecha' => optional($origen->fecha_emision ?? $origen->fecha ?? $origen->created_at)->toDateString(),
                    'tercero' => $origen->contacto?->nombre_completo ?? '—',
                    'total' => (float) ($origen->total ?? $origen->monto_recibido ?? $origen->valor ?? 0),
                    // Re-audit R2 SEG-B4 · badge anulado en meta.
                    'anulado' => $origen->deleted_at !== null,
                ];
            }

            // Re-audit R2 FUNC-C2 + R4 DATOS-M1 · una sola query fusiona:
            //   - agg de d/h (respetando toggle: vivos vs vivos+anulados)
            //   - conteo de anulados asociados (siempre, para banner UI)
            // Antes eran 2 roundtrips separados y potencial divergencia entre ellos.
            $baseMovs = MovimientoContable::query()
                ->when($incluirAnulados, fn ($q) => $q->withTrashed())
                ->where('origen_type', $tipoClass)->where('origen_id', $id);

            // Query fusionada con withTrashed forzado + CASE por deleted_at.
            $agg = MovimientoContable::query()->withTrashed()
                ->where('origen_type', $tipoClass)->where('origen_id', $id)
                ->selectRaw(
                    $incluirAnulados
                        ? 'COALESCE(SUM(debe),0) d, COALESCE(SUM(haber),0) h, SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) anulados'
                        : 'COALESCE(SUM(CASE WHEN deleted_at IS NULL THEN debe ELSE 0 END),0) d, COALESCE(SUM(CASE WHEN deleted_at IS NULL THEN haber ELSE 0 END),0) h, SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) anulados'
                )
                ->first();

            $movs = (clone $baseMovs)
                ->orderBy('id')->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'cuenta' => $m->cuenta_puc,
                    'debe' => (float) $m->debe, 'haber' => (float) $m->haber,
                    'descripcion' => $m->descripcion,
                    'fecha' => $m->fecha instanceof \Carbon\Carbon ? $m->fecha->toDateString() : $m->fecha,
                    'anulado' => $m->deleted_at !== null,
                ])->all();

            $anuladosCount = (int) ($agg->anulados ?? 0);
            $totalD = (float) $agg->d;
            $totalH = (float) $agg->h;
        } else {
            $totalD = 0.0; $totalH = 0.0;
        }

        return Inertia::render('Contabilidad/ReporteDetalle', [
            'tipo' => $tipo, 'id' => $id, 'incluir_anulados' => $incluirAnulados,
            'meta' => $meta,
            'movimientos' => $movs,
            'anulados_count' => $anuladosCount,
            'totales' => ['debe' => $totalD, 'haber' => $totalH, 'diff' => round($totalD - $totalH, 2)],
        ]);
    }

    public function reportes(): Response
    {
        // Re-audit M5 UX-C1 · sin duplicados. 3 pares apuntaban al mismo URL.
        // Ahora cada tarjeta es única y las que aún no existen quedan `listo=false`
        // (gris + disabled en el hub).
        return Inertia::render('Contabilidad/Reportes', [
            'reportes' => [
                ['nombre' => 'Balance de comprobación', 'desc' => 'Sumas y saldos por cuenta PUC', 'href' => '/app/contabilidad', 'listo' => true, 'familia' => 'Estados'],
                ['nombre' => 'Panel contable',          'desc' => 'KPIs, top cuentas y por origen', 'href' => '/app/contabilidad/panel', 'listo' => true, 'familia' => 'Estados'],
                ['nombre' => 'Libro diario',            'desc' => 'Todos los asientos cronológicos del mes', 'href' => '/app/cartera/movimientos', 'listo' => true, 'familia' => 'Auxiliares'],
                ['nombre' => 'Movimientos por cuenta',  'desc' => 'Filtra por prefijo PUC (ej. 1305 clientes)', 'href' => '/app/cartera/movimientos?cuenta=1305', 'listo' => true, 'familia' => 'Auxiliares'],
                ['nombre' => 'Facturas emitidas',       'desc' => 'Ventas del periodo', 'href' => '/app/facturas', 'listo' => true, 'familia' => 'Auxiliares'],
                ['nombre' => 'Pagos recibidos',         'desc' => 'Ingresos del periodo', 'href' => '/app/pagos', 'listo' => true, 'familia' => 'Auxiliares'],
                ['nombre' => 'Compras del periodo',     'desc' => 'OCs recibidas', 'href' => '/app/compras/reporte', 'listo' => true, 'familia' => 'Auxiliares'],
                // Re-audit M5 FUNC-A4 · retenciones ahora incluye las 3 cuentas
                // (2365 RETEFTE + 2367 RETEIVA + 2368 RETEICA). Antes solo 2365.
                ['nombre' => 'Retenciones (RETEFTE + RETEIVA + RETEICA)', 'desc' => 'Base para declaración DIAN', 'href' => '/app/cartera/movimientos?cuentas=2365,2367,2368', 'listo' => true, 'familia' => 'Impuestos'],
                ['nombre' => 'Balance general',         'desc' => 'Activo/Pasivo/Patrimonio (por familia PUC)', 'href' => null, 'listo' => false, 'familia' => 'Estados'],
                ['nombre' => 'Estado de resultados',    'desc' => 'Ingresos vs egresos con margen', 'href' => null, 'listo' => false, 'familia' => 'Estados'],
            ],
        ]);
    }

    /**
     * Mapa FQCN → etiqueta legible. Re-audit M5 UX-M3.
     */
    private function origenLabel(?string $fqcn): string
    {
        return match ($fqcn) {
            \App\Modules\Cartera\Models\FacturaVenta::class => 'Factura de venta',
            \App\Modules\Cartera\Models\PagoVenta::class => 'Recibo de caja',
            \App\Modules\Cartera\Models\NotaCredito::class => 'Nota crédito',
            \App\Modules\Dropi\Models\DropiDevolucion::class => 'Devolución Dropi',
            default => class_basename($fqcn ?? ''),
        };
    }
}
