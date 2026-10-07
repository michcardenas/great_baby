<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Compras\Models\DevolucionProveedor;
use App\Modules\Siigo\Support\ValidadorMapeoPucSiigo;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CONT-C5 · Diagnóstico de mapeo PUC → SIIGO.
 *
 * Vista que ejecuta el ValidadorMapeoPucSiigo sobre:
 *   a) las cuentas usadas por los MovimientoContable PENDIENTES de SIIGO
 *      (así vemos exactamente qué va a reventar en el próximo push);
 *   b) los overrides configurados por la contadora en settings;
 *   c) los defaults recomendados.
 *
 * Aracely entra, ve en una tabla "cuenta X no está mapeada → setting Y",
 * clica un link y la configura. Después de corregir, el push pasa sin
 * roundtrips a SIIGO.
 */
class ValidacionPucSiigoController extends Controller implements HasMiddleware
{
    /**
     * A3 FIX · CRÍTICO · sin este guard cualquier user autenticado veía el
     *   PUC vivo + settings SIIGO. Mismo patrón que ContabilidadExtras.
     */
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            abort_unless($r->user()?->esContable(), 403,
                'Solo el equipo contable puede validar mapeo PUC SIIGO.');
            return $next($r);
        })];
    }

    public function index(ValidadorMapeoPucSiigo $validador): Response
    {
        // Cuentas PUC usadas por movimientos contables cuyo origen aún no está
        // sincronizado a SIIGO (siigo_journal_id vacío). Esas son las que
        // van a reventar en el próximo push si no están mapeadas.
        $pucsEnUso = MovimientoContable::query()
            ->whereNull('siigo_journal_id')
            ->distinct()
            ->pluck('cuenta_puc')
            ->filter()
            ->values()
            ->all();

        $diagnostico = $validador->validarCuentas($pucsEnUso);

        // Cuántos documentos están BLOQUEADOS por cada PUC faltante.
        $impacto = [];
        foreach ($diagnostico['faltantes'] as $f) {
            $impacto[$f['puc']] = MovimientoContable::query()
                ->whereNull('siigo_journal_id')
                ->where('cuenta_puc', $f['puc'])
                ->distinct('origen_type', 'origen_id')
                ->count(DB::raw('CONCAT(origen_type, origen_id)'));
        }

        // Estado de los 7 settings críticos (editables en /app/empresa/reglas).
        $settingsCriticos = [
            'siigo.doc_type_compra',
            'siigo.doc_type_nc_compra',
            'siigo.doc_type_egreso',
            'siigo.cta_cxp_proveedor_siigo',
            'siigo.cta_inventario_siigo',
            'siigo.cta_iva_dev_compra_siigo',
            'siigo.tax_id_iva_19',
            'siigo.payment_type_compra_default',
            'siigo.payment_type_egreso',
        ];
        $settings = [];
        foreach ($settingsCriticos as $k) {
            $v = setting($k);
            $settings[$k] = [
                'valor' => $v === null || $v === '' || $v === 0 || $v === '0' ? null : (string) $v,
                'configurado' => ! ($v === null || $v === '' || $v === 0 || $v === '0'),
            ];
        }

        // Cuentas que SIIGO rechazó de verdad. El validador de arriba sólo sabe
        // del PUC local; esto es lo que la contraparte respondió y es la lista
        // exacta de cuentas que hay que mapear en Plan de cuentas.
        $rechazadasSiigo = \App\Modules\Siigo\Models\SiigoSyncLog::query()
            ->where('recurso', 'cuentas_rechazadas')
            ->latest('id')->limit(100)->get()
            ->groupBy(fn ($l) => data_get($l->detalle, 'cuenta'))
            ->map(fn ($g, $cuenta) => [
                'cuenta' => (string) $cuenta,
                'veces' => $g->count(),
                'ultimo' => optional($g->first()->created_at)->format('Y-m-d H:i'),
                'mensaje' => (string) $g->first()->mensaje,
                'mapeada' => \App\Modules\Siigo\Support\CuentasSiigo::codigo((string) $cuenta) !== (string) $cuenta,
            ])
            ->values()->all();

        return Inertia::render('Contabilidad/ValidacionPucSiigo', [
            'rechazadas_siigo' => $rechazadasSiigo,
            'resumen' => [
                'pucs_en_uso' => count($pucsEnUso),
                'pucs_ok' => count($diagnostico['resultados']) - count($diagnostico['faltantes']),
                'pucs_faltantes' => count($diagnostico['faltantes']),
                'settings_configurados' => collect($settings)->where('configurado', true)->count(),
                'settings_totales' => count($settings),
            ],
            'resultados' => collect($diagnostico['resultados'])->map(fn ($r, $puc) => array_merge(['puc' => (string) $puc], $r, ['impacto' => $impacto[(string) $puc] ?? 0]))->values()->all(),
            'faltantes' => $diagnostico['faltantes'],
            'settings' => $settings,
        ]);
    }
}
