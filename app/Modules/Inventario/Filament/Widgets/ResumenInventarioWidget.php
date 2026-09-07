<?php

namespace App\Modules\Inventario\Filament\Widgets;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Inventario\Enums\EstadoTraslado;
use App\Modules\Inventario\Filament\Resources\AlertaStockConfigResource;
use App\Modules\Inventario\Filament\Resources\TrasladoResource;
use App\Modules\Inventario\Models\AlertaStockDisparada;
use App\Modules\Inventario\Models\ReservaInventario;
use App\Modules\Inventario\Models\Traslado;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ResumenInventarioWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Inventario y Logística';

    protected static ?int $sort = 29;

    protected function getStats(): array
    {
        $alertas = AlertaStockDisparada::where('resuelta', false)->count();
        $trasladosPendientes = Traslado::where('estado', EstadoTraslado::Borrador)->count();
        $trasladosTransito = Traslado::where('estado', EstadoTraslado::EnTransito)->count();
        $reservasActivas = ReservaInventario::where('activa', true)->sum('cantidad');
        $movimientosHoy = InventarioMovimiento::whereDate('created_at', today())->count();

        return [
            Stat::make('Alertas de stock', $alertas)
                ->description('Configuraciones disparadas sin resolver')
                ->color($alertas > 0 ? 'danger' : 'success')
                ->url(AlertaStockConfigResource::getUrl('index')),

            Stat::make('Traslados abiertos', $trasladosPendientes + $trasladosTransito)
                ->description("{$trasladosPendientes} borrador · {$trasladosTransito} en tránsito")
                ->color('warning')
                ->url(TrasladoResource::getUrl('index')),

            Stat::make('Reservas activas', number_format($reservasActivas, 0, ',', '.'))
                ->description('Unidades comprometidas por ventas/pedidos')
                ->color('info'),

            Stat::make('Movimientos hoy', $movimientosHoy)
                ->description('Entradas/salidas/traslados/ajustes')
                ->color('primary'),
        ];
    }
}
