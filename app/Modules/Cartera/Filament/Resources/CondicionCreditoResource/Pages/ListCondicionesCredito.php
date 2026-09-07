<?php

namespace App\Modules\Cartera\Filament\Resources\CondicionCreditoResource\Pages;

use App\Modules\Cartera\Filament\Resources\CondicionCreditoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCondicionesCredito extends ListRecords
{
    protected static string $resource = CondicionCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
