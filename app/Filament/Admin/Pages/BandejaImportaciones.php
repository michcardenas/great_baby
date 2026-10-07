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

    /**
     * Esta página no tenía ninguna guarda: `Page::canAccess()` devuelve true por
     * defecto, así que cualquiera que entrara a `/admin` veía el historial de
     * importaciones con el nombre de quién corrió cada una. Ahora pide su
     * sección, la misma que gatea `/app/bandeja-importaciones`.
     */
    public static function canAccess(): bool
    {
        return \App\Auth\Permisos::puede(auth()->user(), 'bandeja_importaciones');
    }

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
