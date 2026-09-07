<?php

namespace App\Modules\Cartera\Filament\Pages;

use App\Models\Contacto;
use App\Modules\Cartera\Enums\ClasificacionDiferencia;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Enums\TramoAntiguedad;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\PagoVenta;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReportesCartera extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Reportes Cartera';

    protected static ?string $title = 'Reportes de cartera';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 9;

    protected string $view = 'cartera.pages.reportes-cartera';

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    /**
     * Reporte "Edad de saldos" — matriz de clientes × tramos con subtotales.
     */
    public function getEdadSaldos(): array
    {
        $facturas = FacturaVenta::query()
            ->whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])
            ->with('contacto')
            ->get();

        $matriz = [];
        foreach ($facturas as $f) {
            $ct = $f->contacto?->nombreDisplay() ?? '—';
            $tramo = $f->tramo()->value;
            $matriz[$ct][$tramo] = ($matriz[$ct][$tramo] ?? 0) + (float) $f->saldo;
            $matriz[$ct]['_total'] = ($matriz[$ct]['_total'] ?? 0) + (float) $f->saldo;
        }
        uasort($matriz, fn ($a, $b) => ($b['_total'] ?? 0) <=> ($a['_total'] ?? 0));
        return $matriz;
    }

    /**
     * Top 20 clientes con mayor saldo vencido.
     */
    public function getTopMorosos()
    {
        return FacturaVenta::query()
            ->whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])
            ->select('contacto_id', DB::raw('SUM(saldo) as total_saldo'), DB::raw('COUNT(*) as num_facturas'), DB::raw('MAX(DATEDIFF(CURDATE(), fecha_vencimiento)) as mora_max'))
            ->groupBy('contacto_id')
            ->orderByDesc('total_saldo')
            ->limit(20)
            ->with('contacto')
            ->get();
    }

    /**
     * Consignaciones por aclarar — pagos sin identificar o huérfanos.
     */
    public function getConsignacionesPorAclarar()
    {
        return PagoVenta::query()
            ->where(function ($q) {
                $q->where('clasificacion_diferencia', ClasificacionDiferencia::NoIdentificado->value)
                  ->orWhereNull('factura_id');
            })
            ->orderByDesc('fecha')
            ->limit(50)
            ->get();
    }

    /**
     * Descuentos aplicados en el periodo.
     */
    public function getDescuentosAplicados(): array
    {
        $mesActual = PagoVenta::query()
            ->whereIn('clasificacion_diferencia', ['descuento_pronto_pago', 'descuento_fuera_plazo', 'flete_asumido_gb'])
            ->whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year)
            ->selectRaw('clasificacion_diferencia, SUM(diferencia) as total, COUNT(*) as num')
            ->groupBy('clasificacion_diferencia')
            ->get();

        $out = [];
        foreach ($mesActual as $r) {
            $c = ClasificacionDiferencia::from($r->clasificacion_diferencia);
            $out[] = ['label' => $c->label(), 'total' => (float) $r->total, 'num' => (int) $r->num, 'color' => $c->color()];
        }
        return $out;
    }
}
