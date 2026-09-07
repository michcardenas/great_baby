<?php

namespace App\Modules\Compras\Filament\Resources\OrdenCompraResource\Pages;

use App\Modules\Compras\Actions\RecalcularTotalesOC;
use App\Modules\Compras\Filament\Resources\OrdenCompraResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrdenCompra extends EditRecord
{
    protected static string $resource = OrdenCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        RecalcularTotalesOC::run($this->record);
    }
}
