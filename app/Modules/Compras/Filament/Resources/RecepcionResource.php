<?php

namespace App\Modules\Compras\Filament\Resources;

use App\Modules\Compras\Actions\RecibirMercancia;
use App\Modules\Compras\Filament\Resources\RecepcionResource\Pages;
use App\Modules\Compras\Models\RecepcionCompra;
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

class RecepcionResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = RecepcionCompra::class;

    /**
     * Re-audit M2 PATRÓN E (SEG-C1) · Recepciones confirmadas son inmutables
     * (kardex + asiento generados). Solo `borrador` puede editarse/eliminarse.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ($record->estado ?? '') === 'borrador';
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ($record->estado ?? '') === 'borrador';
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Recepciones';

    protected static ?string $modelLabel = 'Recepción';

    protected static ?string $pluralModelLabel = 'Recepciones de compra';

    protected static string|\UnitEnum|null $navigationGroup = 'Compras e Importaciones';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'recepciones-compra';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos')->schema([
                TextInput::make('numero')->disabled()->dehydrated(false),
                Select::make('orden_id')->label('OC origen')
                    ->relationship('orden', 'numero', fn ($q) => $q->whereIn('estado', ['aprobada', 'parcial']))
                    ->searchable()->preload()->required(),
                Select::make('bodega_id')->relationship('bodega', 'nombre')->required()->searchable(),
                DatePicker::make('fecha_recepcion')->native(false)->default(now())->required(),
                TextInput::make('remision_proveedor'),
                TextInput::make('factura_proveedor'),
                TextInput::make('transportista'),
                Textarea::make('observaciones')->columnSpanFull(),
            ])->columns(3),

            Section::make('Ítems recibidos')->schema([
                Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Select::make('orden_item_id')->label('Línea OC')
                            ->relationship('ordenItem', 'descripcion')->searchable()->required(),
                        TextInput::make('cantidad_recibida')->numeric()->required()->step(0.001),
                        TextInput::make('costo_unit')->numeric()->required()->step(0.0001)->prefix('$'),
                        TextInput::make('lote'),
                        DatePicker::make('fecha_vencimiento')->native(false),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->searchable()->weight('bold')->copyable(),
                TextColumn::make('orden.numero')->label('OC')->searchable(),
                TextColumn::make('orden.proveedor.nombre_completo')->label('Proveedor')->limit(28),
                TextColumn::make('bodega.nombre')->badge()->toggleable(),
                TextColumn::make('fecha_recepcion')->date('Y-m-d')->sortable(),
                BadgeColumn::make('estado')->colors([
                    'gray' => 'borrador',
                    'success' => 'confirmada',
                    'danger' => 'anulada',
                ]),
                TextColumn::make('total_recibido')->money('COP')->alignEnd(),
                TextColumn::make('items_count')->counts('items')->badge()->label('Ítems'),
            ])
            ->defaultSort('fecha_recepcion', 'desc')
            ->filters([
                SelectFilter::make('estado')->options([
                    'borrador' => 'Borrador', 'confirmada' => 'Confirmada', 'anulada' => 'Anulada',
                ]),
                SelectFilter::make('bodega_id')->relationship('bodega', 'nombre')->preload(),
            ])
            ->recordActions([
                Action::make('confirmar')
                    ->label('Confirmar recepción')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Se moverá el stock a la bodega y se generarán los asientos contables. Esta acción no se puede revertir automáticamente.')
                    ->visible(fn (RecepcionCompra $r) => $r->estado === 'borrador')
                    ->action(function (RecepcionCompra $record) {
                        try {
                            RecibirMercancia::run($record);
                            Notification::make()->title('Mercancía recibida')
                                ->body('Se movió el stock a bodega y quedaron los asientos contables.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('No se pudo confirmar la recepción')
                                ->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (RecepcionCompra $r) => route('compras.recepcion.pdf', $r))
                    ->openUrlInNewTab(),
                EditAction::make()->visible(fn (RecepcionCompra $r) => $r->estado === 'borrador'),
                DeleteAction::make()->visible(fn (RecepcionCompra $r) => $r->estado === 'borrador'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecepciones::route('/'),
            'edit' => Pages\EditRecepcion::route('/{record}/edit'),
        ];
    }
}
