<?php
namespace App\Modules\Catalogo\Filament\Resources\ColorResource\Pages;
use App\Modules\Catalogo\Filament\Resources\ColorResource;
use Filament\Actions\CreateAction; use Filament\Resources\Pages\ListRecords;
class ListColores extends ListRecords {
    protected static string $resource = ColorResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
