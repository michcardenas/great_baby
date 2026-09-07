<?php

namespace App\Modules\Inventario\Filament\Resources;

use App\Modules\Inventario\Filament\Resources\AlertaStockConfigResource\Pages;
use App\Modules\Inventario\Models\AlertaStockConfig;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AlertaStockConfigResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = AlertaStockConfig::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Alertas de stock';

    protected static ?string $modelLabel = 'Alerta de stock';

    protected static ?string $pluralModelLabel = 'Configuración de alertas';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario y Logística';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'alertas-stock';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Configuración')->schema([
                Select::make('variante_id')->label('Variante')
                    ->relationship('variante', 'codigo_barras')->required()->searchable()->preload(),
                Select::make('ubicacion_id')->label('Bodega (opcional)')
                    ->relationship('ubicacion', 'nombre')->searchable()->preload()
                    ->helperText('Vacío = aplica en cualquier bodega'),

                TextInput::make('stock_minimo')->numeric()->required()->default(0),
                TextInput::make('stock_maximo')->numeric()->nullable(),
                TextInput::make('punto_reorden')->numeric()->nullable()
                    ->helperText('Cuando llegue a este saldo, se sugiere OC'),
                TextInput::make('cantidad_reorden')->numeric()->nullable()
                    ->helperText('Cuánto pedir cuando dispara reorden'),

                Toggle::make('notificar_email')->default(true),
                Toggle::make('notificar_whatsapp'),
                Toggle::make('activa')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variante.codigo_barras')->label('Variante')->searchable()->copyable(),
                TextColumn::make('variante.producto.nombre')->limit(30)->label('Producto'),
                TextColumn::make('ubicacion.nombre')->badge()->color('info')->placeholder('Cualquiera'),
                TextColumn::make('stock_minimo')->alignEnd()->badge()->color('warning'),
                TextColumn::make('punto_reorden')->alignEnd()->badge()->color('info'),
                TextColumn::make('cantidad_reorden')->alignEnd()->toggleable(),
                TextColumn::make('stock_maximo')->alignEnd()->toggleable(),
                IconColumn::make('activa')->boolean(),
            ])
            ->defaultSort('stock_minimo', 'desc')
            ->filters([
                TernaryFilter::make('activa'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('Todavía no configuras alertas')
            ->emptyStateDescription('Cuando el saldo de una variante baje del mínimo o llegue al punto de reorden, te avisamos por correo o WhatsApp para que hagas la orden a tiempo.')
            ->emptyStateIcon('heroicon-o-bell-alert');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlertasStock::route('/'),
            'create' => Pages\CreateAlertaStock::route('/create'),
            'edit' => Pages\EditAlertaStock::route('/{record}/edit'),
        ];
    }
}
