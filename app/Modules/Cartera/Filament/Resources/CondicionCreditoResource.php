<?php

namespace App\Modules\Cartera\Filament\Resources;

use App\Modules\Cartera\Filament\Resources\CondicionCreditoResource\Pages;
use App\Modules\Cartera\Models\CondicionCredito;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CondicionCreditoResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = CondicionCredito::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Condiciones de crédito';

    protected static ?string $modelLabel = 'Condición';

    protected static ?string $pluralModelLabel = 'Condiciones de crédito';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'condiciones-credito';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('contacto_id')
                ->options(fn () => \App\Models\Contacto::query()
                    ->where('es_cliente_b2b', true)
                    ->orderBy('nombre_completo')->pluck('nombre_completo', 'id')->all())
                ->searchable()->required()
                ->label('Cliente B2B'),
            TextInput::make('cupo')->numeric()->prefix('$')->required(),
            TextInput::make('plazo_dias')->numeric()->default(30)->suffix('días'),
            TextInput::make('descuento_pronto_pago_pct')->numeric()->suffix('%')->default(0),
            TextInput::make('plazo_pronto_pago_dias')->numeric()->suffix('días')->default(10),
            Toggle::make('flete_asumido_gb')->label('Flete asumido por GB'),
            Toggle::make('activa')->default(true),
            DatePicker::make('vigente_desde')->required()->default(now())->native(false),
            DatePicker::make('vigente_hasta')->native(false),
            Textarea::make('notas')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contacto.nombre_completo')->searchable()->label('Cliente')->weight('bold'),
                TextColumn::make('cupo')->money('COP')->alignEnd()->sortable(),
                TextColumn::make('plazo_dias')->suffix(' d')->alignEnd()->sortable(),
                TextColumn::make('descuento_pronto_pago_pct')->suffix('%')->alignEnd(),
                TextColumn::make('vigente_desde')->date('Y-m-d'),
                TextColumn::make('vigente_hasta')->date('Y-m-d')->placeholder('—'),
                IconColumn::make('activa')->boolean(),
                IconColumn::make('flete_asumido_gb')->boolean()->label('Flete GB'),
            ])
            ->defaultSort('vigente_desde', 'desc')
            ->filters([
                TernaryFilter::make('activa'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCondicionesCredito::route('/'),
            'create' => Pages\CreateCondicionCredito::route('/create'),
            'edit' => Pages\EditCondicionCredito::route('/{record}/edit'),
        ];
    }
}
