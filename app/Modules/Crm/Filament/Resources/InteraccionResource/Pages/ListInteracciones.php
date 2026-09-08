<?php

namespace App\Modules\Crm\Filament\Resources\InteraccionResource\Pages;

use App\Modules\Crm\Filament\Resources\InteraccionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInteracciones extends ListRecords
{
    protected static string $resource = InteraccionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
