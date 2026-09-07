<?php

namespace App\Modules\Cartera\Filament\Widgets;

use App\Modules\Cartera\Actions\CalcularAntiguedadCartera;
use App\Modules\Cartera\Enums\TramoAntiguedad;
use App\Modules\Cartera\Filament\Resources\FacturaVentaResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * §7 TO-BE Cartera — Semáforo consolidado.
 * Muestra saldos por tramo. Cada tarjeta es clickeable y filtra facturas.
 */
class SemaforoCarteraWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Semáforo de cartera';

    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $r = CalcularAntiguedadCartera::run();
        $fmt = fn ($v) => '$' . number_format($v, 0, ',', '.');
        $stats = [];

        foreach (TramoAntiguedad::cases() as $t) {
            $data = $r['por_tramo'][$t->value] ?? ['count' => 0, 'monto' => 0.0];
            if ($t === TramoAntiguedad::AlDia && $data['count'] === 0) continue;

            $stats[] = Stat::make($t->label(), $fmt($data['monto']))
                ->description("{$data['count']} facturas · " . round($r['total'] > 0 ? ($data['monto'] / $r['total']) * 100 : 0, 1) . '% del total')
                ->color($t->color())
                ->url(FacturaVentaResource::getUrl('index'));
        }

        return $stats;
    }
}
