<?php
namespace App\Modules\Catalogo\Filament\Resources\MarcaResource\Pages;
use App\Modules\Catalogo\Filament\Resources\MarcaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditMarca extends EditRecord
{
    protected static string $resource = MarcaResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
