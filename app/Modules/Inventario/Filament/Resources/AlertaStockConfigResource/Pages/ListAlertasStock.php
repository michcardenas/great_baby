<?php

namespace App\Modules\Inventario\Filament\Resources\AlertaStockConfigResource\Pages;

use App\Modules\Inventario\Filament\Resources\AlertaStockConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAlertasStock extends ListRecords
{
    protected static string $resource = AlertaStockConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /**
     * Fix COD1 ALTA re-audit · eager-load para evitar N+1 en la columna sujeto.
     *   Antes cada fila hacía $record->variante->producto->nombre + $record->producto->nombre
     *   = 2-3 selects extra por fila → paginación de 25 ≈ 75 queries.
     */
    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()?->with(['variante.producto', 'producto', 'ubicacion']);
    }
}
