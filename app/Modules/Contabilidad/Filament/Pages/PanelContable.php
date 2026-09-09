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

    // Re-audit M5 R4 FUNC-A2 · el panel operativo vive en /app/contabilidad/panel
    // (Inertia/Vue) con toggle de anulados y filtros consistentes. Este Filament
    // page es legacy y divergía (sin toggle → cifras distintas para el mismo
    // periodo). Se oculta del menú Filament — sigue accesible por URL a admins
    // para debug, pero Aracely no tropieza con "dos paneles" contradictorios.
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        // Re-audit M5 SEG-C2 · esContable() unificado (Aracely/Gerencia/Gerente/Contador).
        return auth()->user()?->esContable() ?? false;
    }

    public function getStats(): array
    {
        // Re-audit M5 R4 FUNC-A3 · usar `whereBetween(startOfMonth, endOfMonth)`
        // en zona Colombia — evita edge cases si el server persiste `fecha` con
        // TZ ≠ Bogotá (whereMonth+whereYear comparan la columna cruda).
        // Re-audit M5 R4 DATOS-A2 · `consignaciones` limitado a últimos 12 meses
        // — tras 1 año el pago se judicializa/castiga, mantenerlo en el KPI de
        // "pendiente" solo infla. Además rescata perf: full-scan histórico era
        // 3-8s con 100k pagos.
        $ahoraBog = now('America/Bogota');
        $inicioMes = $ahoraBog->copy()->startOfMonth()->toDateString();
        $finMes = $ahoraBog->copy()->endOfMonth()->toDateString();
        $hace12m = $ahoraBog->copy()->subMonths(12)->toDateString();

        return [
            'cartera_total' => (float) FacturaVenta::whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])->sum('saldo'),
            'por_conciliar' => MovimientoContable::query()
                ->whereIn('cuenta_puc', ['1105', '1110'])
                ->whereBetween('fecha', [$inicioMes, $finMes])
                ->count(),
            'consignaciones' => (float) PagoVenta::query()
                ->where(fn ($q) => $q
                    ->where('clasificacion_diferencia', ClasificacionDiferencia::NoIdentificado->value)
                    ->orWhereNull('factura_id'))
                ->where('fecha', '>=', $hace12m)
                ->sum('monto_recibido'),
            'comisiones_periodo' => null,
        ];
    }

    public function getPorConciliar(): array
    {
        // Re-audit M5 FUNC-A2 + BAJO-B2 · quita el hardcode "Conciliado" que
        // ocultaba arqueos descuadrados (riesgo de fraude interno enterrado
        // tras dashboard verde). Ahora "Pendiente" hasta que exista modelo
        // conciliaciones. Filtro de mes agregado para no traer historia.
        $ahoraBog = now('America/Bogota');
        return MovimientoContable::query()
            ->whereIn('cuenta_puc', ['1105', '1110'])
            ->whereYear('fecha', $ahoraBog->year)
            ->whereMonth('fecha', $ahoraBog->month)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($m) {
                // Re-audit M5 DATOS-M4 · cast explícito porque decimal:2 devuelve string.
                $debe = (float) $m->debe;
                $haber = (float) $m->haber;
                return [
                    'fecha' => $m->fecha->format('Y-m-d'),
                    'concepto' => $m->descripcion ?? '—',
                    'valor' => ($debe > 0 ? '+$' : '-$') . number_format(max($debe, $haber), 0, ',', '.'),
                    'tipo' => $debe > 0 ? 'ingreso' : 'egreso',
                    'clasificacion' => 'Cuenta PUC ' . $m->cuenta_puc,
                    'estado' => 'Pendiente',
                ];
            })->all();
    }
}
