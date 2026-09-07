<?php
namespace App\Modules\Catalogo\Filament\Resources\CategoriaResource\Pages;
use App\Modules\Catalogo\Filament\Resources\CategoriaResource;
use Filament\Actions\DeleteAction; use Filament\Resources\Pages\EditRecord;
class EditCategoria extends EditRecord {
    protected static string $resource = CategoriaResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
