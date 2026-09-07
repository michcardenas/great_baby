<?php

namespace App\Modules\Cartera\Filament\Resources\ContactoResource\Pages;

use App\Modules\Cartera\Filament\Resources\ContactoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContactos extends ListRecords
{
    protected static string $resource = ContactoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
