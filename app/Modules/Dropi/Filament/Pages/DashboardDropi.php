<?php

namespace App\Modules\Dropi\Filament\Pages;

use App\Modules\Dropi\Filament\Resources\DropiPedidoResource;
use App\Modules\Dropi\Filament\Widgets\AlertasOperativasWidget;
use App\Modules\Dropi\Filament\Widgets\EmbudoPedidosWidget;
use App\Modules\Dropi\Filament\Widgets\MetricasFinancierasWidget;
use App\Modules\Dropi\Filament\Widgets\ResumenCorteActivoWidget;
use App\Modules\Dropi\Models\DropiPedido;
use App\Support\Periodos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class DashboardDropi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard Dropi';

    protected static string|\UnitEnum|null $navigationGroup = 'Dropi';

    protected static ?int $navigationSort = 0;

    protected string $view = 'dropi.pages.dashboard-dropi';

    public string $periodo = Periodos::MES;

    public string $guiaBuscada = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();
        if (! $u) return false;
        return $u->esAracely() || $u->hasAnyRole(['Gerente', 'Contador']);
    }

    public function mount(): void
    {
        $this->periodo = Periodos::actual();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('periodo')
                ->label('Periodo')
                ->options(Periodos::opciones())
                ->live()
                ->afterStateUpdated(fn ($state) => Periodos::guardar($state)),
            TextInput::make('guiaBuscada')
                ->label('Buscar guía')
                ->placeholder('GUI-000001')
                ->suffixIcon('heroicon-o-magnifying-glass'),
        ]);
    }

    public function irAGuia(): void
    {
        $guia = trim($this->guiaBuscada);
        if ($guia === '') {
            return;
        }
        $existe = DropiPedido::where('guia', $guia)->exists();
        if (! $existe) {
            Notification::make()->title("Guía {$guia} no encontrada")->warning()->send();
            return;
        }
        $this->redirect(DropiPedidoResource::getUrl('index', ['tableSearch' => $guia]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ir_guia')
                ->label('Ir a guía')
                ->icon('heroicon-o-arrow-right')
                ->action('irAGuia'),
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            ResumenCorteActivoWidget::class,
            MetricasFinancierasWidget::class,
            AlertasOperativasWidget::class,
            EmbudoPedidosWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'lg' => 4];
    }
}
