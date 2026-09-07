<?php

namespace App\Modules\Cartera\Filament\Resources\ContactoResource\Pages;

use App\Modules\Cartera\Filament\Resources\ContactoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditContacto extends EditRecord
{
    protected static string $resource = ContactoResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
