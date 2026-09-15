<?php

namespace App\Modules\Contabilidad\Filament\Pages;

use App\Modules\Cartera\Models\MovimientoContable;
use BackedEnum;
use Filament\Pages\Page;

/**
 * Estado de Resultados (P&L) calculado desde los asientos (movimientos_contables)
 * por clase PUC: 4=Ingresos, 6=Costo de ventas, 5=Gastos operacionales.
 * Es un estado de PERIODO (usa el rango de fechas).
 */
class EstadoResultados extends Page
{
    protected static ?string $slug = 'contabilidad/estado-resultados';

    protected string $view = 'contabilidad.pages.estado-resultados';

    protected static ?string $navigationLabel = 'Estado de resultados';

    protected static ?string $title = 'Estado de Resultados (P&L)';

    protected static string|\UnitEnum|null $navigationGroup = 'Contabilidad';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    public ?string $desde = null;
    public ?string $hasta = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public function mount(): void
    {
        $hoy = now('America/Bogota');
        $this->desde = $this->desde ?? $hoy->copy()->startOfYear()->toDateString();
        $this->hasta = $this->hasta ?? $hoy->toDateString();
    }

    /**
     * Neto por cuenta dentro del rango, clasificado por naturaleza:
     * ingresos (clase 4) son de naturaleza crédito; costos/gastos (5,6) débito.
     *
     * @return array<string,mixed>
     */
    public function datos(): array
    {
        $desde = $this->desde ?: now('America/Bogota')->startOfYear()->toDateString();
        $hasta = $this->hasta ?: now('America/Bogota')->toDateString();

        $filas = MovimientoContable::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('cuenta_puc, LEFT(cuenta_puc,1) AS clase, SUM(debe) AS d, SUM(haber) AS h')
            ->groupBy('cuenta_puc')
            ->orderBy('cuenta_puc')
            ->get();

        $ingresos = [];
        $costos = [];
        $gastos = [];
        $totIng = 0.0;
        $totCosto = 0.0;
        $totGasto = 0.0;

        foreach ($filas as $f) {
            $d = (float) $f->d;
            $h = (float) $f->h;
            switch ($f->clase) {
                case '4': // ingresos — naturaleza crédito
                    $val = $h - $d;
                    if (abs($val) < 0.01) break;
                    $ingresos[] = ['cuenta' => $f->cuenta_puc, 'valor' => $val];
                    $totIng += $val;
                    break;
                case '6': // costo de ventas — naturaleza débito
                    $val = $d - $h;
                    if (abs($val) < 0.01) break;
                    $costos[] = ['cuenta' => $f->cuenta_puc, 'valor' => $val];
                    $totCosto += $val;
                    break;
                case '5': // gastos — naturaleza débito
                    $val = $d - $h;
                    if (abs($val) < 0.01) break;
                    $gastos[] = ['cuenta' => $f->cuenta_puc, 'valor' => $val];
                    $totGasto += $val;
                    break;
            }
        }

        $utilidadBruta = $totIng - $totCosto;
        $utilidadOperacional = $utilidadBruta - $totGasto;

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'ingresos' => $ingresos,
            'costos' => $costos,
            'gastos' => $gastos,
            'totIngresos' => $totIng,
            'totCostos' => $totCosto,
            'totGastos' => $totGasto,
            'utilidadBruta' => $utilidadBruta,
            'margenBruto' => $totIng > 0 ? round($utilidadBruta / $totIng * 100, 1) : 0,
            'utilidadOperacional' => $utilidadOperacional,
            'margenOperacional' => $totIng > 0 ? round($utilidadOperacional / $totIng * 100, 1) : 0,
        ];
    }
}
