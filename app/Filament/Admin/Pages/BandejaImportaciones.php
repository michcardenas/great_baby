<?php

namespace App\Filament\Admin\Pages;

use App\Models\ImportacionBandeja;
use BackedEnum;
use Filament\Pages\Page;

class BandejaImportaciones extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Bandeja de importaciones';

    protected static ?string $title = 'Bandeja de importaciones';

    protected static string|\UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'bandeja-importaciones';

    protected string $view = 'admin.pages.bandeja-importaciones';

    public function importaciones()
    {
        return ImportacionBandeja::with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function stats(): array
    {
        return [
            'total' => ImportacionBandeja::count(),
            'corriendo' => ImportacionBandeja::where('estado', 'corriendo')->count(),
            'terminadas_hoy' => ImportacionBandeja::where('estado', 'terminado')->whereDate('terminada_at', today())->count(),
            'fallidas' => ImportacionBandeja::where('estado', 'fallido')->count(),
        ];
    }
}
