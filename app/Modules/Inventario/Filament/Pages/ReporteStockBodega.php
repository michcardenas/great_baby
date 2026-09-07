<?php

namespace App\Modules\Inventario\Filament\Pages;

use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Inventario\Services\StockService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReporteStockBodega extends Page
{
    protected string $view = 'inventario.pages.reporte-stock';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationLabel = 'Stock por bodega';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario y Logística';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'stock-bodegas';

    public ?int $ubicacionId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->esAracely(), 403);
        $this->ubicacionId = InventarioUbicacion::where('activa', true)->orderBy('id')->value('id');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public function bodegas()
    {
        return InventarioUbicacion::where('activa', true)->orderBy('nombre')->get();
    }

    public function saldos(): array
    {
        if (! $this->ubicacionId) {
            return [];
        }

        return DB::table('inventario_movimientos as m')
            ->join('producto_variantes as v', 'v.id', '=', 'm.variante_id')
            ->leftJoin('productos as p', 'p.id', '=', 'v.producto_id')
            ->where('m.ubicacion_id', $this->ubicacionId)
            ->selectRaw('v.id as variante_id, v.codigo_barras, v.color_nombre, v.talla,
                p.referencia, p.nombre as producto_nombre,
                SUM(m.cantidad) as saldo')
            ->groupBy('v.id', 'v.codigo_barras', 'v.color_nombre', 'v.talla', 'p.referencia', 'p.nombre')
            ->havingRaw('saldo != 0')
            ->orderByDesc('saldo')
            ->get()
            ->toArray();
    }
}
