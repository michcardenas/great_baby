<?php

namespace App\Modules\Cartera\Filament\Resources\CondicionCreditoResource\Pages;

use App\Modules\Cartera\Filament\Resources\CondicionCreditoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCondicionCredito extends EditRecord
{
    protected static string $resource = CondicionCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
