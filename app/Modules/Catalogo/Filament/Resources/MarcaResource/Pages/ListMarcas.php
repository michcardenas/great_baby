<?php
namespace App\Modules\Catalogo\Filament\Resources\MarcaResource\Pages;
use App\Modules\Catalogo\Filament\Resources\MarcaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListMarcas extends ListRecords
{
    protected static string $resource = MarcaResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
