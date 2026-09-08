<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Actions\CalcularComisionMensual;
use App\Modules\Crm\Models\ComisionCalculada;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class CerrarComisionMensual extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Cerrar comisión mensual';

    protected static ?string $title = 'Cerrar comisión mensual · Vendedores';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'cerrar-comision';

    protected string $view = 'crm.pages.cerrar-comision';

    public int $anio;
    public int $mes;

    public static function canAccess(): bool
    {
        $u = auth()->user();
        return $u && ($u->esAracely() || $u->hasAnyRole(['Gerente', 'Contador']));
    }

    public function mount(): void
    {
        $this->anio = (int) now()->year;
        $this->mes = (int) now()->month;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('anio')->label('Año')
                ->options(collect(range(now()->year - 2, now()->year + 1))
                    ->mapWithKeys(fn ($y) => [$y => $y])->toArray())
                ->default(now()->year)->required(),
            Select::make('mes')->label('Mes')
                ->options([1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'])
                ->default(now()->month)->required(),
        ])->columns(2);
    }

    public function calcular(): void
    {
        $resultados = CalcularComisionMensual::run($this->anio, $this->mes);
        Notification::make()
            ->title('Comisiones calculadas')
            ->body(count($resultados) . ' vendedor(es) procesado(s). Revisá abajo el detalle.')
            ->success()->send();
    }

    public function calculadasDelMes()
    {
        return ComisionCalculada::with(['vendedor', 'aprobador'])
            ->where('anio', $this->anio)->where('mes', $this->mes)
            ->orderByDesc('total_a_pagar')->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calcular')->label('🧮 Calcular comisiones')
                ->color('primary')->action('calcular')
                ->requiresConfirmation()
                ->modalDescription('Se calcularán las comisiones de todos los vendedores activos para el mes seleccionado. Los cálculos aprobados/pagados no se sobrescriben.'),
        ];
    }
}
