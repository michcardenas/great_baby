<?php

namespace App\Modules\Contabilidad\Filament\Resources\ConciliacionBancariaResource\Pages;

use App\Modules\Contabilidad\Filament\Resources\ConciliacionBancariaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConciliaciones extends ListRecords
{
    protected static string $resource = ConciliacionBancariaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
