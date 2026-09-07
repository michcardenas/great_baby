<?php

namespace App\Modules\Compras\Filament\Resources\OrdenCompraResource\Pages;

use App\Modules\Compras\Filament\Resources\OrdenCompraResource;
use App\Modules\Compras\Models\OrdenCompra;
use Filament\Resources\Pages\Page;

class ViewOrdenCompra extends Page
{
    protected static string $resource = OrdenCompraResource::class;

    protected string $view = 'compras.pages.view-orden';

    public OrdenCompra $record;

    public function mount(int|string $record): void
    {
        $this->record = OrdenCompra::with(['items.producto', 'items.variante', 'proveedor', 'bodega', 'recepciones'])
            ->findOrFail($record);
    }
}
