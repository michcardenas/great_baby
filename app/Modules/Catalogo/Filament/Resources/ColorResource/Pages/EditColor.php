<?php
namespace App\Modules\Catalogo\Filament\Resources\ColorResource\Pages;
use App\Modules\Catalogo\Filament\Resources\ColorResource;
use Filament\Actions\DeleteAction; use Filament\Resources\Pages\EditRecord;
class EditColor extends EditRecord {
    protected static string $resource = ColorResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
