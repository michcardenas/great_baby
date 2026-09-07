<?php

namespace App\Modules\Dropi\Filament\Widgets;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use Filament\Widgets\ChartWidget;

/**
 * §22 diseño Dropi — Embudo por etapa: la primera vista real del estado integral
 * del pedido que hoy no existe en ningún sistema del cliente.
 */
class EmbudoPedidosWidget extends ChartWidget
{
    protected ?string $heading = 'Embudo por etapa · Pedidos Dropi';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $conteos = DropiPedido::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $labels = [];
        $data = [];
        $colors = [];

        foreach (EstadoPedidoDropi::cases() as $estado) {
            $labels[] = $estado->label();
            $data[] = (int) ($conteos[$estado->value] ?? 0);
            $colors[] = $this->color($estado);
        }

        return [
            'datasets' => [[
                'label' => 'Pedidos',
                'data' => $data,
                'backgroundColor' => $colors,
                'borderWidth' => 0,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function color(EstadoPedidoDropi $e): string
    {
        return match ($e->color()) {
            'success' => '#22c55e',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',
            default => '#71717a',
        };
    }

    protected function getOptions(): array
    {
        // Barras horizontales: los labels largos (11 estados) se leen sin cortarse.
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]],
            ],
        ];
    }
}
