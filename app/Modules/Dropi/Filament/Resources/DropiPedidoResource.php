<?php

namespace App\Modules\Dropi\Filament\Resources;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Filament\Resources\DropiPedidoResource\Pages;
use App\Modules\Dropi\Models\DropiPedido;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DropiPedidoResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = DropiPedido::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Pedidos Dropi';

    protected static ?string $modelLabel = 'Pedido';

    protected static ?string $pluralModelLabel = 'Pedidos Dropi';

    protected static string|\UnitEnum|null $navigationGroup = 'Dropi';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'guia';

    public static function getGloballySearchableAttributes(): array
    {
        return ['guia', 'cliente_nombre', 'cliente_ciudad', 'dropi_orden_id', 'transportadora'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Cliente' => $record->cliente_nombre . ' · ' . $record->cliente_ciudad,
            'Estado' => $record->estado?->label(),
            'Monto' => '$' . number_format((float) $record->monto_esperado_proveedor, 0, ',', '.'),
        ];
    }

    // §24 — todos los roles del panel pueden VER pedidos, pero solo Aracely edita campos financieros (protegido en form)
    public static function canViewAny(): bool
    {
        return (bool) auth()->user();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación del pedido')->schema([
                Select::make('corte_id')
                    ->relationship('corte', 'fecha')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->etiqueta() ?? '—')
                    ->required()
                    ->searchable(),
                TextInput::make('guia')->label('Guía')->required()->unique(ignoreRecord: true)
                    ->helperText('§11 diseño Dropi — identificador principal, único en el sistema.'),
                TextInput::make('dropi_orden_id')->label('ID orden Dropi'),
                TextInput::make('transportadora'),
                TextInput::make('tienda'),
            ])->columns(2),

            Section::make('Vendedor dropshipping')->schema([
                TextInput::make('vendedor_nombre')->label('Nombre del vendedor'),
                TextInput::make('vendedor_identificacion')->label('Identificación'),
                Toggle::make('requiere_factura_b2b')
                    ->label('Facturar B2B a este vendedor')
                    ->helperText('§21 — vendedores del sondeo se facturan B2B directo; el resto va a consumidor final.'),
            ])->columns(3),

            Section::make('Cliente final (envío)')->schema([
                TextInput::make('cliente_nombre')->required(),
                TextInput::make('cliente_doc')->label('Documento'),
                TextInput::make('cliente_telefono')->label('Teléfono'),
                TextInput::make('cliente_direccion')->label('Dirección')->columnSpanFull(),
                TextInput::make('cliente_ciudad')->label('Ciudad'),
                TextInput::make('cliente_depto')->label('Departamento'),
            ])->columns(3),

            Section::make('Ciclo de vida (lectura)')
                ->description('El estado y las fechas se actualizan solo por Acciones (sync, alistamiento, conciliación). Ver bitácora abajo.')
                ->schema([
                    Select::make('estado')
                        ->options(collect(EstadoPedidoDropi::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()]))
                        ->disabled()->dehydrated(false),
                    DateTimePicker::make('despachado_at')->label('Despachado')->native(false)->disabled()->dehydrated(false),
                    DateTimePicker::make('entregado_at')->label('Entregado')->native(false)->disabled()->dehydrated(false),
                    DateTimePicker::make('devuelto_at')->label('Devuelto')->native(false)->disabled()->dehydrated(false),
                    DateTimePicker::make('pagado_at')->label('Pagado')->native(false)->disabled()->dehydrated(false),
                ])->columns(2),

            Section::make('Valores (lectura)')
                ->description('Los montos vienen de la sincronización con Dropi y del catálogo de proveedor GB. No editable a mano.')
                ->schema([
                    TextInput::make('monto_esperado_proveedor')
                        ->label('Monto esperado (precio proveedor GB)')
                        ->numeric()->prefix('$')->disabled()->dehydrated(false),
                    TextInput::make('monto_cliente_final')->label('Total al cliente')->numeric()->prefix('$')->disabled()->dehydrated(false),
                    TextInput::make('ganancia_vendedor')->label('Ganancia vendedor')->numeric()->prefix('$')->disabled()->dehydrated(false),
                    TextInput::make('flete_transportadora')->label('Flete')->numeric()->prefix('$')->disabled()->dehydrated(false),
                ])->columns(2),

            Section::make('Facturación (lectura)')
                ->schema([
                    TextInput::make('ari_factura_id')->label('ID factura ARI')->disabled()->dehydrated(false),
                    DateTimePicker::make('ari_enviado_at')->label('Enviado a ARI')->native(false)->disabled()->dehydrated(false),
                    TextInput::make('nota_credito_id')->label('Nota crédito')->disabled()->dehydrated(false),
                ])->columns(2)->collapsible()->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guia')->label('Guía')->searchable()->weight('bold')->copyable(),
                TextColumn::make('corte.fecha')->label('Corte')
                    ->formatStateUsing(fn ($state, $record) => $record->corte
                        ? 'C' . $record->corte->numero . ' · ' . $record->corte->fecha->format('m-d')
                        : '—')
                    ->badge()->color('gray')->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('cliente_nombre')->label('Cliente')->searchable()->limit(25),
                TextColumn::make('cliente_ciudad')->label('Ciudad')->searchable()->toggleable(),
                TextColumn::make('transportadora')->badge()->color('gray')->toggleable(),
                BadgeColumn::make('estado')
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoPedidoDropi ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoPedidoDropi ? $state->color() : 'gray'),
                TextColumn::make('monto_esperado_proveedor')
                    ->label('Esperado')->money('COP')->alignEnd()->sortable(),
                TextColumn::make('despachado_at')->label('Despachado')->dateTime('Y-m-d H:i')->toggleable(isToggledHiddenByDefault: true)->sortable(),
                TextColumn::make('pagado_at')->label('Pagado')->dateTime('Y-m-d H:i')->toggleable(isToggledHiddenByDefault: true)->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->options(collect(EstadoPedidoDropi::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),
                SelectFilter::make('corte_id')
                    ->label('Corte')
                    ->relationship('corte', 'fecha')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->etiqueta() ?? '—'),
                SelectFilter::make('transportadora')
                    ->options(fn () => \App\Modules\Dropi\Models\DropiPedido::query()
                        ->whereNotNull('transportadora')
                        ->distinct()->pluck('transportadora', 'transportadora')->toArray()),
                SelectFilter::make('cliente_ciudad')->label('Ciudad')
                    ->options(fn () => \App\Modules\Dropi\Models\DropiPedido::query()
                        ->whereNotNull('cliente_ciudad')
                        ->distinct()->pluck('cliente_ciudad', 'cliente_ciudad')->toArray()),
                Filter::make('rango_fechas')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('desde')->native(false),
                        \Filament\Forms\Components\DatePicker::make('hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        $chips = [];
                        if ($data['desde'] ?? null) $chips[] = 'Desde ' . $data['desde'];
                        if ($data['hasta'] ?? null) $chips[] = 'Hasta ' . $data['hasta'];
                        return $chips;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('table')
                                ->fromTable()
                                ->withFilename('pedidos-dropi-' . now()->format('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDropiPedidos::route('/'),
            'create' => Pages\CreateDropiPedido::route('/create'),
            'view' => Pages\ViewDropiPedido::route('/{record}'),
            'edit' => Pages\EditDropiPedido::route('/{record}/edit'),
        ];
    }
}
