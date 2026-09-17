<?php

namespace App\Modules\Cartera\Filament\Resources\MetodoPagoResource\Pages;

use App\Modules\Cartera\Filament\Resources\MetodoPagoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMetodosPago extends ListRecords
{
    protected static string $resource = MetodoPagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo método')
                ->modalWidth(Width::TwoExtraLarge),
        ];
    }
}
