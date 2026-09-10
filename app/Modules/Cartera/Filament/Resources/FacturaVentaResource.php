<?php

namespace App\Modules\Cartera\Filament\Resources;

use App\Modules\Cartera\Actions\RegistrarPago;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Filament\Resources\FacturaVentaResource\Pages;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Siigo\Services\SiigoEmisionService;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FacturaVentaResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = FacturaVenta::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Facturas de venta';

    protected static ?string $modelLabel = 'Factura';

    protected static ?string $pluralModelLabel = 'Facturas de venta';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'facturas-venta';

    public static function canViewAny(): bool
    {
        // Fix D1 · matriz Permisos (antes esAracely() bloqueaba Contador).
        return \App\Auth\Permisos::puede(auth()->user(), 'facturas');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'contacto.nombre_completo', 'contacto.razon_social'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos generales')->schema([
                TextInput::make('numero')
                    ->required()->unique(ignoreRecord: true)->helperText('Ej: FV-000001')
                    // Re-audit RAÍZ F (DATOS #5) · post-emisión el consecutivo es intocable (DIAN).
                    ->disabled(fn (?FacturaVenta $record) => $record?->emitida_at !== null)
                    ->dehydrated(fn (?FacturaVenta $record) => $record?->emitida_at === null),
                Select::make('contacto_id')
                    ->label('Cliente')
                    ->options(fn () => \App\Models\Contacto::query()
                        ->where('es_cliente', true)
                        ->orderBy('nombre_completo')->pluck('nombre_completo', 'id')->all())
                    ->searchable()->required()
                    // Re-audit R3-02 · post-emisión el tercero DIAN es inmutable
                    // (el saving() del modelo también lo bloquea — defensa en profundidad).
                    ->disabled(fn (?FacturaVenta $record) => $record?->emitida_at !== null)
                    ->dehydrated(fn (?FacturaVenta $record) => $record?->emitida_at === null),
                DatePicker::make('fecha_emision')->required()->native(false)
                    // Re-audit RAÍZ F (DATOS #5) · post-emisión inmutable — la fecha
                    // de emisión determina el mes fiscal y NO puede cambiarse.
                    ->disabled(fn (?FacturaVenta $record) => $record?->emitida_at !== null)
                    ->dehydrated(fn (?FacturaVenta $record) => $record?->emitida_at === null),
                DatePicker::make('fecha_vencimiento')->required()->native(false),
                Select::make('vendedor_id')->relationship('vendedor', 'name')->searchable(),
                Textarea::make('observaciones')->columnSpanFull(),
            ])->columns(2),

            Section::make('Ítems')->schema([
                Repeater::make('items')
                    ->relationship()
                    ->schema([
                        Select::make('variante_id')
                            ->label('Variante')
                            ->relationship('variante', 'codigo_barras')
                            ->searchable()->preload()->required(),
                        TextInput::make('descripcion')->required(),
                        TextInput::make('cantidad')->numeric()->minValue(0.001)->default(1)->required(),
                        TextInput::make('precio_unit')->numeric()->prefix('$')->required(),
                        TextInput::make('descuento_pct')->numeric()->suffix('%')->default(0),
                        TextInput::make('impuesto_pct')->numeric()->suffix('%')->default(19),
                        TextInput::make('subtotal')->numeric()->prefix('$')->disabled()->dehydrated(false)
                            ->helperText('Se calcula al guardar (cantidad × precio - descuento)'),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->addActionLabel('+ Agregar ítem')
                    ->reorderable(false)
                    ->collapsible(),
            ]),

            Section::make('Valores')->schema([
                // Re-audit R3-02 · valores económicos DIAN son inmutables post-emisión.
                // El saving() del modelo lanza RuntimeException; disabled aquí
                // hace la restricción visible en UI y evita el 500.
                TextInput::make('subtotal')->numeric()->prefix('$')->default(0)->required()
                    ->disabled(fn (?FacturaVenta $record) => $record?->emitida_at !== null)
                    ->dehydrated(fn (?FacturaVenta $record) => $record?->emitida_at === null),
                TextInput::make('descuento')->numeric()->prefix('$')->default(0)
                    ->disabled(fn (?FacturaVenta $record) => $record?->emitida_at !== null)
                    ->dehydrated(fn (?FacturaVenta $record) => $record?->emitida_at === null),
                TextInput::make('impuestos')->numeric()->prefix('$')->default(0)
                    ->disabled(fn (?FacturaVenta $record) => $record?->emitida_at !== null)
                    ->dehydrated(fn (?FacturaVenta $record) => $record?->emitida_at === null),
                TextInput::make('total')->numeric()->prefix('$')->required()->helperText('subtotal - descuento + impuestos')
                    ->disabled(fn (?FacturaVenta $record) => $record?->emitida_at !== null)
                    ->dehydrated(fn (?FacturaVenta $record) => $record?->emitida_at === null),
                TextInput::make('saldo')->numeric()->prefix('$')->disabled()->dehydrated(false)->helperText('Se recalcula con los pagos'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->searchable()->weight('bold')->copyable(),
                TextColumn::make('contacto.nombre_completo')->label('Cliente')->searchable()->limit(28),
                TextColumn::make('fecha_emision')->date('Y-m-d')->sortable(),
                TextColumn::make('fecha_vencimiento')->date('Y-m-d')->sortable()
                    ->color(fn (FacturaVenta $r) => $r->diasMora() > 0 ? 'danger' : 'gray'),
                TextColumn::make('tramo_display')
                    ->label('Antigüedad')
                    ->badge()
                    ->getStateUsing(fn (FacturaVenta $r) => $r->tramo()->label())
                    ->color(fn (FacturaVenta $r) => $r->tramo()->color()),
                BadgeColumn::make('estado')
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoFactura ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoFactura ? $state->color() : 'gray'),
                TextColumn::make('total')->money('COP')->alignEnd()->sortable(),
                TextColumn::make('saldo')->money('COP')->alignEnd()->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
            ])
            ->defaultSort('fecha_vencimiento', 'asc')
            ->filters([
                SelectFilter::make('estado')
                    ->options(collect(EstadoFactura::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),
                SelectFilter::make('contacto_id')->relationship('contacto', 'nombre_completo')->searchable()->preload()->label('Cliente'),
            ])
            ->headerActions([
                Action::make('importar')
                    ->label('Importar facturas')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->modalHeading('Importar facturas de venta')
                    ->modalDescription('Sigue los 3 pasos. La plantilla trae ejemplos ligados a clientes existentes.')
                    ->modalContent(view('cartera.modals.importar-facturas'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (FacturaVenta $r) => route('cartera.factura.pdf', $r))
                    ->openUrlInNewTab(),
                Action::make('emitir_dian')
                    ->label(fn (FacturaVenta $r) => $r->es_electronica ? 'Re-consultar DIAN' : 'Emitir a DIAN')
                    ->icon('heroicon-o-paper-airplane')
                    ->color(fn (FacturaVenta $r) => $r->es_electronica ? 'gray' : 'primary')
                    ->visible(fn (FacturaVenta $r) => ! $r->es_electronica)
                    ->requiresConfirmation()
                    ->modalHeading('Emitir factura electrónica DIAN')
                    ->modalDescription(fn (FacturaVenta $r) => "Se enviará {$r->numero} a la DIAN vía SIIGO con stamp.send=true. El CUFE y el QR se guardarán al recibir la respuesta.")
                    ->modalSubmitActionLabel('Emitir ahora')
                    ->action(function (FacturaVenta $record) {
                        // Anti doble-click: throttle por factura durante 30s
                        $lockKey = "emitir_dian:{$record->id}";
                        if (! \Illuminate\Support\Facades\Cache::add($lockKey, 1, 30)) {
                            Notification::make()->title('Emisión en curso')
                                ->body('Esta factura ya se está emitiendo. Esperá unos segundos.')
                                ->warning()->send();
                            return;
                        }
                        try {
                            $svc = app(SiigoEmisionService::class);
                            $svc->emitir($record->fresh(['contacto', 'items.variante.producto']));
                            $record->refresh();
                            Notification::make()
                                ->title('Factura enviada a DIAN')
                                ->body("SIIGO nº {$record->numero_siigo} · Stamp: {$record->stamp_status}"
                                    . ($record->cufe ? ' · CUFE ' . substr($record->cufe, 0, 20) . '…' : ''))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo emitir')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        } finally {
                            \Illuminate\Support\Facades\Cache::forget($lockKey);
                        }
                    }),
                Action::make('link_publico')
                    ->label('Link para el cliente')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->visible(fn (FacturaVenta $r) => (bool) $r->token_publico)
                    ->modalHeading('Link público de esta factura')
                    ->modalDescription(fn (FacturaVenta $r) => 'Copia y envía este link al cliente. No requiere login.')
                    ->modalContent(fn (FacturaVenta $r) => view('cartera.modals.link-publico', [
                        'url' => route('cartera.factura.publica', $r->token_publico),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('registrar_pago')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->visible(fn (FacturaVenta $r) => $r->saldo > 0)
                    ->schema([
                        TextInput::make('monto_recibido')->numeric()->prefix('$')->required()
                            ->default(fn (FacturaVenta $r) => (float) $r->saldo)
                            ->helperText('Si es menor al saldo se clasifica automáticamente (pronto pago / flete / etc.)'),
                        DatePicker::make('fecha')->default(now())->native(false)->required(),
                        Select::make('medio_pago')
                            ->options([
                                'transferencia' => 'Transferencia',
                                'efectivo' => 'Efectivo',
                                'tarjeta' => 'Tarjeta',
                                'nequi' => 'Nequi',
                                'daviplata' => 'Daviplata',
                                'otro' => 'Otro',
                            ])->default('transferencia')->required(),
                        TextInput::make('referencia')->label('Referencia / número de transacción'),
                        TextInput::make('banco'),
                        Textarea::make('notas'),
                    ])
                    ->action(function (FacturaVenta $record, array $data) {
                        // Idempotency: bloquea doble-click por 15s con hash del payload
                        $key = 'pago:' . $record->id . ':' . md5(($data['referencia'] ?? '') . '|' . $data['monto_recibido'] . '|' . $data['fecha']);
                        if (! \Illuminate\Support\Facades\Cache::add($key, 1, 15)) {
                            Notification::make()->title('Duplicado detectado')
                                ->body('Ya se está registrando este mismo pago. Esperá unos segundos.')
                                ->warning()->send();
                            return;
                        }
                        try {
                            $pago = RegistrarPago::run(
                                facturaId: $record->id,
                                montoRecibido: (float) $data['monto_recibido'],
                                fecha: $data['fecha'],
                                medioPago: $data['medio_pago'],
                                referencia: $data['referencia'] ?? null,
                                banco: $data['banco'] ?? null,
                                userId: auth()->id(),
                                notas: $data['notas'] ?? null,
                            );
                            Notification::make()
                                ->title('Pago registrado')
                                ->body("Aplicado $" . number_format((float) $pago->monto_aplicado, 0, ',', '.') .
                                       " · Diferencia clasificada como: " . ($pago->clasificacion_diferencia?->label() ?? '—'))
                                ->success()->send();
                        } catch (\RuntimeException $e) {
                            // Re-audit UX N1 · antes las guardas de negocio
                            // (factura no puede recibir pago, saving inmutable,
                            // partida doble descuadrada) reventaban con la
                            // pantalla roja Laravel Whoops. Ahora se traducen
                            // a Notification::danger con el mensaje humano.
                            Notification::make()
                                ->title('No se pudo registrar el pago')
                                ->body($e->getMessage())
                                ->danger()->persistent()->send();
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('[Filament pago] error inesperado', [
                                'factura_id' => $record->id, 'error' => $e->getMessage(),
                            ]);
                            Notification::make()
                                ->title('Error inesperado al registrar el pago')
                                ->body('Se registró el error para revisión. Intenta de nuevo.')
                                ->danger()->send();
                        } finally {
                            \Illuminate\Support\Facades\Cache::forget($key);
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('facturas')
                                ->fromTable()
                                ->withFilename('facturas-venta-' . now()->format('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacturasVenta::route('/'),
            'create' => Pages\CreateFacturaVenta::route('/create'),
            'view' => Pages\ViewFacturaVenta::route('/{record}'),
            'edit' => Pages\EditFacturaVenta::route('/{record}/edit'),
        ];
    }
}
