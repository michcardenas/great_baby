<?php

namespace App\Modules\Contabilidad\Filament\Resources;

use App\Modules\Contabilidad\Filament\Resources\PlanCuentaResource\Pages;
use App\Modules\Contabilidad\Models\PlanCuenta;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de PLAN DE CUENTAS (PUC) — dominio de Silvia (Contabilidad).
 * Crear/editar en MODAL; carga masiva y plantilla desde la página de lista.
 */
class PlanCuentaResource extends Resource
{
    protected static ?string $model = PlanCuenta::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Plan de cuentas';

    protected static ?string $modelLabel = 'Cuenta';

    protected static ?string $pluralModelLabel = 'Plan de cuentas';

    protected static string|\UnitEnum|null $navigationGroup = 'Contabilidad';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'plan-cuentas';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'sm' => 2])
            ->components([
                TextInput::make('codigo')
                    ->label('Código PUC')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->placeholder('Ej: 1105, 4135, 413505')
                    ->helperText('Solo dígitos. 1=Activo 2=Pasivo 3=Patrimonio 4=Ingresos 5=Gastos 6=Costo ventas.')
                    ->columnSpan(1),
                TextInput::make('nombre')
                    ->label('Nombre de la cuenta')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('Ej: Caja general')
                    ->columnSpan(1),
                Select::make('naturaleza')
                    ->label('Naturaleza')
                    ->native(false)
                    ->placeholder('Automática según la clase')
                    ->options(['debito' => 'Débito', 'credito' => 'Crédito'])
                    ->helperText('Déjalo vacío para que la calcule la clase (Activo/Gasto/Costo = Débito).')
                    ->columnSpan(1),
                TextInput::make('siigo_cuenta_id')
                    ->label('Cuenta SIIGO (opcional)')
                    ->maxLength(40)
                    ->placeholder('ID/código equivalente en SIIGO')
                    ->columnSpan(1),
                Toggle::make('permite_movimiento')
                    ->label('Permite movimiento (cuenta de detalle)')
                    ->default(true)
                    ->helperText('Apágalo en cuentas mayores (clase/grupo) que solo agrupan.')
                    ->inline(false)
                    ->columnSpan(1),
                Toggle::make('activa')
                    ->label('Activa')
                    ->default(true)
                    ->inline(false)
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->badge()->searchable()->sortable(),
                TextColumn::make('nombre')->label('Nombre')->searchable()->wrap(),
                TextColumn::make('clase')->label('Clase')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? $state . ' · ' . PlanCuenta::nombreClase($state) : '—')
                    ->color(fn ($state) => match ($state) {
                        '1' => 'success', '2' => 'warning', '3' => 'info',
                        '4' => 'primary', '5' => 'danger', '6' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('nivel')->label('Nivel')
                    ->formatStateUsing(fn ($state) => [1 => 'Clase', 2 => 'Grupo', 3 => 'Cuenta', 4 => 'Subcuenta', 5 => 'Auxiliar'][$state] ?? $state)
                    ->badge()->color('gray')->toggleable(),
                TextColumn::make('naturaleza')->label('Naturaleza')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst((string) $state))
                    ->color(fn ($state) => $state === 'debito' ? 'info' : 'warning'),
                IconColumn::make('permite_movimiento')->label('Movimiento')->boolean()->toggleable(),
                TextColumn::make('siigo_cuenta_id')->label('SIIGO')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->defaultSort('codigo')
            ->filters([
                SelectFilter::make('clase')->label('Clase')->options(PlanCuenta::CLASES),
                SelectFilter::make('naturaleza')->label('Naturaleza')
                    ->options(['debito' => 'Débito', 'credito' => 'Crédito']),
                SelectFilter::make('nivel')->label('Nivel')->options([
                    1 => 'Clase', 2 => 'Grupo', 3 => 'Cuenta', 4 => 'Subcuenta', 5 => 'Auxiliar',
                ]),
                TernaryFilter::make('permite_movimiento')->label('Permite movimiento'),
                TernaryFilter::make('activa')->label('Activa'),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->after(fn () => PlanCuenta::vincularPadres()),
                DeleteAction::make()->after(fn () => PlanCuenta::vincularPadres()),
            ]);
    }

    public static function getPages(): array
    {
        // Solo index: crear y editar en MODAL; importar/plantilla en el header.
        return [
            'index' => Pages\ListPlanCuentas::route('/'),
        ];
    }
}
