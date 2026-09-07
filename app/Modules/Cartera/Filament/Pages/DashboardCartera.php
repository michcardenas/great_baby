<?php

namespace App\Modules\Cartera\Filament\Pages;

use App\Modules\Cartera\Filament\Widgets\SemaforoCarteraWidget;
use BackedEnum;
use Filament\Pages\Page;

class DashboardCartera extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Dashboard Cartera';

    protected static ?string $title = 'Dashboard · Cartera y Cobranza';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 0;

    protected string $view = 'cartera.pages.dashboard-cartera';

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public function getHeaderWidgets(): array
    {
        return [SemaforoCarteraWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'lg' => 6];
    }
}
