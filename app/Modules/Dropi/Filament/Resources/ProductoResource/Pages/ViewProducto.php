<?php

namespace App\Modules\Dropi\Filament\Resources\ProductoResource\Pages;

use App\Modules\Dropi\Filament\Resources\ProductoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProducto extends ViewRecord
{
    protected static string $resource = ProductoResource::class;

    protected string $view = 'catalogo.pages.view-producto';

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    public function getVariantes()
    {
        return $this->record->variantes()
            ->with(['color', 'diseno', 'tallaMaestra'])
            ->orderBy('codigo_barras')
            ->get();
    }

    public function getStockPorUbicacion(): array
    {
        $stock = \DB::table('inventario_movimientos')
            ->join('producto_variantes', 'inventario_movimientos.variante_id', '=', 'producto_variantes.id')
            ->join('inventario_ubicaciones', 'inventario_movimientos.ubicacion_id', '=', 'inventario_ubicaciones.id')
            ->where('producto_variantes.producto_id', $this->record->id)
            ->groupBy('producto_variantes.codigo_barras', 'inventario_ubicaciones.codigo', 'inventario_ubicaciones.nombre')
            ->selectRaw('producto_variantes.codigo_barras as sku, inventario_ubicaciones.codigo as ubi, inventario_ubicaciones.nombre as ubi_nombre, SUM(inventario_movimientos.cantidad) as saldo')
            ->having('saldo', '!=', 0)
            ->get();

        return $stock->groupBy('sku')->map->all()->toArray();
    }
}
