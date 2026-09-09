<?php

namespace App\Modules\Compras\Filament\Resources;

use App\Modules\Compras\Actions\AprobarOrdenCompra;
use App\Modules\Compras\Actions\PrepararRecepcionDesdeOC;
use App\Modules\Compras\Actions\RecalcularTotalesOC;
use App\Modules\Compras\Enums\EstadoOrdenCompra;
use App\Modules\Compras\Filament\Resources\OrdenCompraResource\Pages;
use App\Modules\Compras\Models\OrdenCompra;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OrdenCompraResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = OrdenCompra::class;

    /**
     * Re-audit M2 PATRÓN E (SEG-C1) · sobrescribe `canEdit/canDelete` del trait
     * `HeredaAutorizacion` para verificar ESTADO. Antes esconder el botón
     * `->visible()` no cerraba la URL Livewire → admin editaba OC Aprobada/
     * Recibida por URL directa y quedaba inconsistente vs asientos.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->estado === EstadoOrdenCompra::Borrador;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->estado === EstadoOrdenCompra::Borrador;
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Órdenes de compra';

    protected static ?string $modelLabel = 'Orden de compra';

    protected static ?string $pluralModelLabel = 'Órdenes de compra';

    protected static string|\UnitEnum|null $navigationGroup = 'Compras e Importaciones';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ordenes-compra';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'proveedor.nombre_completo', 'proveedor.razon_social'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos generales')->schema([
                TextInput::make('numero')->disabled()->dehydrated(false)
                    ->placeholder('Se genera automático al guardar (OC-YYYY-000000)'),
                Select::make('proveedor_id')
                    ->label('Proveedor')
                    ->relationship('proveedor', 'nombre_completo', fn ($q) => $q->where('es_proveedor', true))
                    ->searchable()->required()->preload(),
                Select::make('bodega_id')->label('Bodega destino')
                    ->relationship('bodega', 'nombre')->searchable()->preload(),
                Select::make('tipo')->options([
                    'nacional' => 'Nacional',
                    'importacion' => 'Importación',
                ])->default('nacional')->required()->live(),
                Select::make('moneda')->options([
                    'COP' => 'COP', 'USD' => 'USD', 'EUR' => 'EUR', 'CNY' => 'CNY',
                ])->default('COP')->required(),
                TextInput::make('tasa_cambio')->numeric()->default(1)->step(0.000001)
                    ->helperText('Solo aplica si la moneda ≠ COP'),
                DatePicker::make('fecha_esperada')->native(false)
                    ->helperText('Fecha comprometida por el proveedor'),
                Select::make('condicion_credito_id')
                    ->label('Condición de pago')
                    ->relationship('condicionCredito', 'id')->searchable(),
                Textarea::make('observaciones')->columnSpanFull(),
            ])->columns(2),

            Section::make('Ítems')->schema([
                Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Select::make('producto_id')
                            ->relationship('producto', 'nombre')
                            ->searchable()->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) return;
                                $p = \App\Modules\Dropi\Models\Producto::find($state);
                                if ($p) {
                                    $set('descripcion', $p->nombre);
                                    $set('precio_unit', (float) $p->precio_proveedor);
                                }
                            }),
                        Select::make('variante_id')
                            ->label('Variante')
                            ->options(function (callable $get) {
                                $prodId = $get('producto_id');
                                if (! $prodId) return [];
                                return \App\Modules\Dropi\Models\ProductoVariante::where('producto_id', $prodId)
                                    ->pluck('codigo_barras', 'id');
                            })
                            ->searchable()
                            ->helperText('Selecciona el producto primero, luego aparecerán sólo sus variantes'),
                        TextInput::make('descripcion')->required(),
                        TextInput::make('cantidad')->numeric()->required()->step(0.001)->default(1)->live(onBlur: true),
                        TextInput::make('precio_unit')->numeric()->required()->step(0.0001)->prefix('$')->live(onBlur: true),
                        TextInput::make('descuento_pct')->numeric()->default(0)->suffix('%'),
                        TextInput::make('iva_pct')->numeric()->default(19)->suffix('%')->live(onBlur: true),
                    ])
                    ->columns(4)
                    ->reorderable(false)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string =>
                        ($state['descripcion'] ?? 'Ítem') . ' × ' . ($state['cantidad'] ?? 0)
                    )
                    ->columnSpanFull(),
            ]),

            Section::make('Retenciones y descuentos')->schema([
                TextInput::make('descuento')->numeric()->prefix('$')->default(0),
                TextInput::make('retefuente')->numeric()->prefix('$')->default(0),
                TextInput::make('reteiva')->numeric()->prefix('$')->default(0),
                TextInput::make('reteica')->numeric()->prefix('$')->default(0),
            ])->columns(4)->collapsible()->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->searchable()->weight('bold')->copyable(),
                TextColumn::make('proveedor.nombre_completo')->label('Proveedor')->searchable()->limit(28),
                TextColumn::make('tipo')->badge()
                    ->color(fn ($state) => $state === 'importacion' ? 'warning' : 'info'),
                TextColumn::make('fecha_emision')->date('Y-m-d')->sortable(),
                TextColumn::make('fecha_esperada')->date('Y-m-d')
                    ->color(fn (OrdenCompra $r) => $r->fecha_esperada && $r->fecha_esperada->isPast() && ! in_array($r->estado, [EstadoOrdenCompra::Recibida, EstadoOrdenCompra::Cerrada, EstadoOrdenCompra::Anulada], true) ? 'danger' : 'gray'),
                BadgeColumn::make('estado')
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoOrdenCompra ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoOrdenCompra ? $state->color() : 'gray'),
                TextColumn::make('total')->money('COP')->alignEnd()->sortable(),
                TextColumn::make('porcentaje_recibido')
                    ->label('% recibido')
                    ->getStateUsing(fn (OrdenCompra $r) => $r->porcentajeRecibido() . '%')
                    ->badge()
                    ->color(fn (OrdenCompra $r) => match (true) {
                        $r->porcentajeRecibido() >= 100 => 'success',
                        $r->porcentajeRecibido() > 0 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('fecha_emision', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->options(collect(EstadoOrdenCompra::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),
                SelectFilter::make('proveedor_id')
                    ->relationship('proveedor', 'nombre_completo', fn ($q) => $q->where('es_proveedor', true))
                    ->searchable()->preload()->label('Proveedor'),
                SelectFilter::make('tipo')->options([
                    'nacional' => 'Nacional',
                    'importacion' => 'Importación',
                ]),
                TernaryFilter::make('vencidas')
                    ->label('Con fecha esperada vencida')
                    ->queries(
                        true: fn ($q) => $q->whereDate('fecha_esperada', '<', now())
                            ->whereNotIn('estado', ['recibida', 'cerrada', 'anulada']),
                        false: fn ($q) => $q,
                        blank: fn ($q) => $q,
                    ),
            ])
            ->headerActions([
                Action::make('importar')
                    ->label('Importar OC en lote')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->modalHeading('Importar órdenes de compra')
                    ->modalDescription('Sube la plantilla Excel con las cabeceras + ítems. Cada línea con el mismo número de OC se agrupa.')
                    ->modalContent(view('compras.modals.importar-ordenes'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->recordActions([
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OrdenCompra $r) => in_array($r->estado, [EstadoOrdenCompra::Borrador, EstadoOrdenCompra::Enviada], true))
                    ->action(function (OrdenCompra $record) {
                        try {
                            AprobarOrdenCompra::run($record);
                            Notification::make()->title('OC aprobada')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('No se pudo aprobar la orden')
                                ->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('crear_recepcion')
                    ->label('Crear recepción')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('warning')
                    ->visible(fn (OrdenCompra $r) => in_array($r->estado, [EstadoOrdenCompra::Aprobada, EstadoOrdenCompra::Parcial], true))
                    ->action(function (OrdenCompra $record) {
                        $rec = PrepararRecepcionDesdeOC::run($record);
                        Notification::make()
                            ->title('Recepción creada')
                            ->body("N° {$rec->numero} — Ajusta cantidades y confirma.")
                            ->success()->send();
                    }),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (OrdenCompra $r) => route('compras.orden.pdf', $r))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                // Re-audit M2 PATRÓN E (SEG-C1 / FUNC-A1) · Edit/Delete solo si
                // estado = Borrador. `visible()` esconde botón pero la ruta Livewire
                // seguía abierta; Blindaje adicional en el modelo (`saving()` bloquea
                // campos inmutables post-estado). Aquí visual + soft para el usuario.
                EditAction::make()
                    ->visible(fn (OrdenCompra $r) => $r->estado === EstadoOrdenCompra::Borrador)
                    ->after(fn (OrdenCompra $record) => RecalcularTotalesOC::run($record)),
                DeleteAction::make()->visible(fn (OrdenCompra $r) => $r->estado === EstadoOrdenCompra::Borrador),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('oc')
                                ->fromTable()
                                ->withFilename('ordenes-compra-' . now()->format('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdenesCompra::route('/'),
            'create' => Pages\CreateOrdenCompra::route('/create'),
            'view' => Pages\ViewOrdenCompra::route('/{record}'),
            'edit' => Pages\EditOrdenCompra::route('/{record}/edit'),
        ];
    }
}
