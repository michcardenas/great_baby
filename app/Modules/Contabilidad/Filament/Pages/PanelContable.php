<?php

namespace App\Modules\Contabilidad\Filament\Pages;

use App\Modules\Cartera\Enums\ClasificacionDiferencia;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Cartera\Models\PagoVenta;
use BackedEnum;
use Filament\Pages\Page;

class PanelContable extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Panel contable';

    protected static ?string $title = 'Panel de Contabilidad';

    protected static string|\UnitEnum|null $navigationGroup = 'Contabilidad';

    protected static ?int $navigationSort = 1;

    protected string $view = 'contabilidad.pages.panel';

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public function getStats(): array
    {
        return [
            'cartera_total' => (float) FacturaVenta::whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])->sum('saldo'),
            'por_conciliar' => MovimientoContable::query()
                ->whereIn('cuenta_puc', ['1105', '1110'])
                ->whereMonth('fecha', now()->month)
                ->count(),
            'consignaciones' => (float) PagoVenta::where('clasificacion_diferencia', ClasificacionDiferencia::NoIdentificado->value)
                ->orWhereNull('factura_id')
                ->sum('monto_recibido'),
            'comisiones_periodo' => 0.0, // pendiente hasta M1 CRM/Ventas
        ];
    }

    public function getPorConciliar(): array
    {
        return MovimientoContable::query()
            ->whereIn('cuenta_puc', ['1105', '1110'])
            ->orderByDesc('fecha')
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'fecha' => $m->fecha->format('Y-m-d'),
                'concepto' => $m->descripcion ?? '—',
                'valor' => ($m->debe > 0 ? '+$' : '-$') . number_format((float) max($m->debe, $m->haber), 0, ',', '.'),
                'tipo' => $m->debe > 0 ? 'ingreso' : 'egreso',
                'clasificacion' => 'Cuenta PUC ' . $m->cuenta_puc,
                'estado' => 'Conciliado',
            ])->all();
    }
}
