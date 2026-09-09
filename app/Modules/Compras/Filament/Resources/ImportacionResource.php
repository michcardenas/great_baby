<?php

namespace App\Modules\Compras\Filament\Resources;

use App\Modules\Compras\Actions\LiquidarImportacion;
use App\Modules\Compras\Enums\ConceptoGastoImportacion;
use App\Modules\Compras\Enums\EstadoImportacion;
use App\Modules\Compras\Filament\Resources\ImportacionResource\Pages;
use App\Modules\Compras\Models\Importacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportacionResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = Importacion::class;

    /**
     * Re-audit M2 PATRÓN E (SEG-C1 / FUNC-M2) · Importaciones LIQUIDADAS son
     * inmutables (asiento + kardex generados). Solo EnTransito/EnPuerto/
     * Nacionalizada admiten edición/borrado. Previene cambio de fecha
     * liquidación post-cierre fiscal.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->estado !== \App\Modules\Compras\Enums\EstadoImportacion::Liquidada;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->estado === \App\Modules\Compras\Enums\EstadoImportacion::EnTransito;
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Importaciones';

    protected static ?string $modelLabel = 'Importación';

    protected static ?string $pluralModelLabel = 'Importaciones';

    protected static string|\UnitEnum|null $navigationGroup = 'Compras e Importaciones';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'importaciones';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del contenedor')->schema([
                TextInput::make('numero')->disabled()->dehydrated(false),
                TextInput::make('contenedor')->helperText('Ej: MSCU1234567'),
                TextInput::make('bl_awb')->label('BL / AWB'),
                TextInput::make('proveedor_pais')->label('País de origen'),
                TextInput::make('puerto_origen'),
                TextInput::make('puerto_destino')->default('Cartagena'),
                Select::make('incoterm')->options([
                    'EXW' => 'EXW', 'FOB' => 'FOB', 'CIF' => 'CIF', 'CFR' => 'CFR', 'DAP' => 'DAP', 'DDP' => 'DDP',
                ]),
                Select::make('moneda_origen')->options(['USD' => 'USD', 'EUR' => 'EUR', 'CNY' => 'CNY'])->default('USD'),
                TextInput::make('tasa_cambio_liquidacion')->numeric()->step(0.000001)
                    ->helperText('TRM del día de nacionalización'),
            ])->columns(3),

            Section::make('Fechas del ciclo')->schema([
                DatePicker::make('fecha_zarpe')->native(false),
                DatePicker::make('eta')->label('ETA')->native(false),
                DatePicker::make('fecha_llegada')->native(false),
                DatePicker::make('fecha_nacionalizacion')->native(false),
                DatePicker::make('fecha_liquidacion')->native(false),
            ])->columns(3),

            Section::make('Órdenes vinculadas')->schema([
                Select::make('ordenes')
                    ->label('OC importación asociadas')
                    ->relationship('ordenes', 'numero', fn ($q) => $q->where('tipo', 'importacion'))
                    ->multiple()->searchable()->preload(),
            ]),

            Section::make('Gastos de importación')->schema([
                Repeater::make('gastos')
                    ->relationship('gastos')
                    ->schema([
                        Select::make('concepto')->options(
                            collect(ConceptoGastoImportacion::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])
                        )->required()->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('capitalizable',
                                ConceptoGastoImportacion::tryFrom($state)?->capitalizablePorDefecto() ?? true
                            )),
                        TextInput::make('descripcion')->required(),
                        Select::make('proveedor_id')->relationship('proveedor', 'nombre_completo')->searchable()->label('Proveedor gasto'),
                        Select::make('moneda')->options(['COP' => 'COP', 'USD' => 'USD', 'EUR' => 'EUR'])->default('COP')->required(),
                        TextInput::make('monto')->numeric()->required()->prefix('$'),
                        TextInput::make('monto_base')->numeric()->required()->prefix('$')
                            ->helperText('En COP para prorrateo'),
                        Toggle::make('capitalizable')->default(true)
                            ->helperText('Suma al costo del producto; si off va a gasto (5195)'),
                        Select::make('metodo_prorrateo')->options([
                            'valor' => 'Por valor FOB',
                            'cantidad' => 'Por cantidad',
                            'peso' => 'Por peso',
                            'volumen' => 'Por volumen',
                        ])->default('valor')->required(),
                        TextInput::make('factura_proveedor'),
                        DatePicker::make('fecha')->native(false),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->columnSpanFull(),
            ])->collapsible(),

            Section::make('Observaciones')->schema([
                Textarea::make('observaciones')->columnSpanFull()->rows(2),
            ])->collapsible()->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->searchable()->weight('bold')->copyable(),
                TextColumn::make('contenedor')->searchable()->badge()->color('info'),
                TextColumn::make('proveedor_pais')->badge()->toggleable(),
                TextColumn::make('eta')->date('Y-m-d')->sortable(),
                TextColumn::make('fecha_llegada')->date('Y-m-d')->toggleable(),
                BadgeColumn::make('estado')
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoImportacion ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoImportacion ? $state->color() : 'gray'),
                TextColumn::make('valor_fob')->money('COP')->alignEnd(),
                TextColumn::make('valor_gastos')->money('COP')->alignEnd()->label('Gastos'),
                TextColumn::make('valor_total_costo')->money('COP')->alignEnd()->weight('bold')->label('Costo total'),
                TextColumn::make('ordenes_count')->counts('ordenes')->label('OCs')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->options(collect(EstadoImportacion::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),
            ])
            ->recordActions([
                Action::make('liquidar')
                    ->label('Liquidar')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Se prorratean los gastos capitalizables por el método definido en cada gasto, se calcula el costo final unitario y se generan los asientos contables 1465→1435.')
                    ->visible(fn (Importacion $r) => in_array($r->estado, [EstadoImportacion::Nacionalizada, EstadoImportacion::EnPuerto], true))
                    ->action(function (Importacion $record) {
                        try {
                            LiquidarImportacion::run($record);
                            Notification::make()->title('Importación liquidada')
                                ->body('Costos actualizados y asientos contables generados.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('No se pudo liquidar la importación')
                                ->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('pdf_liquidacion')
                    ->label('PDF liquidación')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Importacion $r) => route('compras.importacion.pdf', $r))
                    ->openUrlInNewTab()
                    ->visible(fn (Importacion $r) => $r->estado === EstadoImportacion::Liquidada),
                EditAction::make(),
                DeleteAction::make()->visible(fn (Importacion $r) => $r->estado === EstadoImportacion::EnTransito),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportaciones::route('/'),
            'create' => Pages\CreateImportacion::route('/create'),
            'edit' => Pages\EditImportacion::route('/{record}/edit'),
        ];
    }
}
