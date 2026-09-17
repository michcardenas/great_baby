<?php

namespace App\Modules\Cartera\Filament\Resources;

use App\Modules\Cartera\Filament\Resources\MetodoPagoResource\Pages;
use App\Modules\Cartera\Models\MetodoPago;
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
 * Maestro de métodos de pago — gestionable por el área contable.
 * El cliente enumera los que usa (efectivo, transferencia, consignación,
 * cruce de cuentas, NEC…) y aquí se definen sus reglas.
 */
class MetodoPagoResource extends Resource
{
    protected static ?string $model = MetodoPago::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Métodos de pago';

    protected static ?string $modelLabel = 'Método de pago';

    protected static ?string $pluralModelLabel = 'Métodos de pago';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'metodos-pago';

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
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(60)
                    ->placeholder('Ej: Consignación, Cruce de cuentas')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if (blank($get('codigo'))) {
                            $set('codigo', \Illuminate\Support\Str::slug((string) $state, '_'));
                        }
                    })
                    ->columnSpan(1),
                TextInput::make('codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->placeholder('Ej: consignacion')
                    ->helperText('Identificador interno. Se sugiere solo del nombre.')
                    ->columnSpan(1),
                Select::make('tipo')
                    ->label('Tipo')
                    ->native(false)
                    ->options(MetodoPago::TIPOS)
                    ->default('otro')
                    ->required()
                    ->columnSpan(1),
                Select::make('cuenta_puc')
                    ->label('Cuenta contable (caja/banco)')
                    ->native(false)
                    ->searchable()
                    ->placeholder('Opcional — a qué cuenta afecta')
                    ->options(fn () => PlanCuenta::query()
                        ->where('activa', true)
                        ->where('permite_movimiento', true)
                        ->orderBy('codigo')
                        ->get(['codigo', 'nombre'])
                        ->mapWithKeys(fn ($c) => [$c->codigo => $c->codigo . ' · ' . $c->nombre])
                        ->all())
                    ->helperText('Del Plan de cuentas. Ej: 110505 Caja general, 111005 Banco.')
                    ->columnSpan(1),
                Toggle::make('requiere_referencia')
                    ->label('Exige referencia / Nº transacción')
                    ->inline(false)
                    ->columnSpan(1),
                Toggle::make('requiere_banco')
                    ->label('Exige banco')
                    ->inline(false)
                    ->columnSpan(1),
                Toggle::make('requiere_comprobante')
                    ->label('Exige adjuntar comprobante')
                    ->inline(false)
                    ->columnSpan(1),
                TextInput::make('orden')
                    ->label('Orden en la lista')
                    ->numeric()
                    ->default(0)
                    ->columnSpan(1),
                Toggle::make('activo')->label('Activo')->default(true)->inline(false)->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orden')->label('#')->sortable()->toggleable(),
                TextColumn::make('nombre')->label('Nombre')->searchable()->weight('bold'),
                TextColumn::make('tipo')->label('Tipo')->badge()
                    ->formatStateUsing(fn ($state) => MetodoPago::TIPOS[$state] ?? $state)
                    ->color('gray'),
                TextColumn::make('cuenta_puc')->label('Cuenta')->placeholder('—')->toggleable(),
                IconColumn::make('requiere_referencia')->label('Ref.')->boolean()->toggleable(),
                IconColumn::make('requiere_banco')->label('Banco')->boolean()->toggleable(),
                IconColumn::make('requiere_comprobante')->label('Comprob.')->boolean(),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->defaultSort('orden')
            ->reorderable('orden')
            ->filters([
                SelectFilter::make('tipo')->options(MetodoPago::TIPOS),
                TernaryFilter::make('activo')->label('Activo'),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make()->modalWidth(Width::TwoExtraLarge),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMetodosPago::route('/'),
        ];
    }
}
