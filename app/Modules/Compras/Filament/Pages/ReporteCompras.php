<?php

namespace App\Modules\Compras\Filament\Pages;

use App\Modules\Compras\Models\Importacion;
use App\Modules\Compras\Models\OrdenCompra;
use App\Modules\Compras\Models\OrdenCompraItem;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReporteCompras extends Page
{
    protected string $view = 'compras.pages.reporte-compras';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Reportes de compras';

    protected static string|\UnitEnum|null $navigationGroup = 'Compras e Importaciones';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'reportes-compras';

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public function getKpis(): array
    {
        $desde = now()->subDays(30);

        return [
            'oc_periodo' => OrdenCompra::where('fecha_emision', '>=', $desde)->count(),
            'monto_periodo' => (float) OrdenCompra::where('fecha_emision', '>=', $desde)
                ->whereNotIn('estado', ['borrador', 'anulada'])->sum('total'),
            'ticket_promedio' => (float) OrdenCompra::where('fecha_emision', '>=', $desde)
                ->whereNotIn('estado', ['borrador', 'anulada'])->avg('total'),
            'contenedores' => Importacion::whereNotIn('estado', ['liquidada', 'cerrada'])->count(),
            'valor_transito' => (float) Importacion::whereNotIn('estado', ['liquidada', 'cerrada'])
                ->sum('valor_fob'),
        ];
    }

    public function getTopProductosCosto(): array
    {
        return OrdenCompraItem::query()
            ->join('compras_ordenes', 'compras_ordenes.id', '=', 'compra_orden_items.orden_id')
            ->join('productos', 'productos.id', '=', 'compra_orden_items.producto_id')
            ->where('compras_ordenes.fecha_emision', '>=', now()->subDays(90))
            ->whereNotIn('compras_ordenes.estado', ['borrador', 'anulada'])
            ->selectRaw('productos.referencia, productos.nombre,
                SUM(compra_orden_items.cantidad) as cant_total,
                AVG(compra_orden_items.precio_unit) as precio_prom,
                SUM(compra_orden_items.total) as monto_total')
            ->groupBy('productos.id', 'productos.referencia', 'productos.nombre')
            ->orderByDesc('monto_total')
            ->limit(20)
            ->get()
            ->toArray();
    }

    public function getEstadoImportaciones(): array
    {
        return Importacion::query()
            ->selectRaw('estado, COUNT(*) as cnt, SUM(valor_total_costo) as valor')
            ->groupBy('estado')
            ->get()
            ->toArray();
    }
}
