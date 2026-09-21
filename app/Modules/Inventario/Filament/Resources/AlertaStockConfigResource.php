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
        // C-F-QA5 · matriz: Gerente y Alistador también gestionan alertas.
        return \App\Auth\Permisos::puede(auth()->user(), 'alertas_stock');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sujeto de la alerta')
                ->description('Selecciona UNA variante (para productos con desglose) O un producto agregado (sin desglose). El motor VerificarAlertasStock evalúa el saldo del sujeto elegido.')
                ->schema([
                    // C-F3 FIX ALTO auditor · antes solo variante_id → los 134 productos
                    //   agregados del cliente no podían tener alertas. Ahora acepta
                    //   variante O producto agregado (mutuamente excluyentes).
                    Select::make('variante_id')->label('Variante (productos granulares)')
                        ->relationship('variante', 'codigo_barras')->searchable()->preload()
                        ->helperText('Deja vacío si es alerta sobre producto agregado.')
                        ->live()
                        ->disabled(fn ($get) => filled($get('producto_id')))
                        // Fix QA E2E · limpiar el otro campo al elegir uno (mutua excl. real).
                        ->afterStateUpdated(function ($state, $set) {
                            if (filled($state)) $set('producto_id', null);
                        }),
                    Select::make('producto_id')->label('Producto agregado (sin desglose)')
                        ->options(fn () => \App\Modules\Dropi\Models\Producto::query()
                            ->where('desglose_stock', false)
                            ->orderBy('referencia')
                            ->pluck('nombre', 'id')
                            ->all())
                        ->searchable()->preload()
                        ->helperText(fn ($get) => filled($get('variante_id'))
                            ? 'Limpia la variante primero para elegir un producto agregado.'
                            : 'Solo se listan productos con stock agregado.')
                        ->live()
                        ->disabled(fn ($get) => filled($get('variante_id')))
                        ->afterStateUpdated(function ($state, $set) {
                            if (filled($state)) $set('variante_id', null);
                        })
                        ->rules([
                            fn ($get) => function ($attribute, $value, $fail) use ($get) {
                                if (blank($value) && blank($get('variante_id'))) {
                                    $fail('Debes elegir una variante O un producto agregado.');
                                }
                                if (filled($value) && filled($get('variante_id'))) {
                                    $fail('No puedes elegir variante Y producto al mismo tiempo.');
                                }
                            },
                        ]),
                    Select::make('ubicacion_id')->label('Bodega (opcional)')
                        ->relationship('ubicacion', 'nombre')->searchable()->preload()
                        ->helperText('Vacío = aplica en cualquier bodega')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Umbrales')->schema([
                TextInput::make('stock_minimo')->numeric()->required()->default(0),
                TextInput::make('stock_maximo')->numeric()->nullable(),
                TextInput::make('punto_reorden')->numeric()->nullable()
                    ->helperText('Cuando llegue a este saldo, se sugiere OC'),
                TextInput::make('cantidad_reorden')->numeric()->nullable()
                    ->helperText('Cuánto pedir cuando dispara reorden'),
            ])->columns(2),

            Section::make('Notificaciones')->schema([
                Toggle::make('notificar_email')->default(true),
                Toggle::make('notificar_whatsapp'),
                Toggle::make('activa')->default(true),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // C-F3 FIX ALTO auditor · sujeto polimórfico en la columna Producto.
                //   Alertas granulares → nombre.producto + code barcode.
                //   Alertas agregadas → nombre del producto + badge "AGREGADO".
                TextColumn::make('sujeto')
                    ->label('Producto / Variante')
                    ->getStateUsing(fn ($record) => $record->esAgregada()
                        ? ($record->producto?->nombre ?? '—').' · AGREGADO'
                        : ($record->variante?->producto?->nombre ?? '—')
                            .($record->variante?->codigo_barras ? ' · '.$record->variante->codigo_barras : ''))
                    ->searchable(false)->limit(50),
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
