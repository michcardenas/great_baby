<?php

namespace App\Modules\Inventario\Filament\Resources;

use App\Modules\Inventario\Actions\EjecutarTraslado;
use App\Modules\Inventario\Enums\EstadoTraslado;
use App\Modules\Inventario\Filament\Resources\TrasladoResource\Pages;
use App\Modules\Inventario\Models\Traslado;
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

class TrasladoResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = Traslado::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Traslados';

    protected static ?string $modelLabel = 'Traslado';

    protected static ?string $pluralModelLabel = 'Traslados de inventario';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario y Logística';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'traslados';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del traslado')->schema([
                TextInput::make('numero')->disabled()->dehydrated(false)
                    ->placeholder('Se genera al guardar (TRA-YYYY-NNNNNN)'),
                Select::make('origen_id')->label('Bodega origen')
                    ->relationship('origen', 'nombre')->required()->searchable()->preload(),
                Select::make('destino_id')->label('Bodega destino')
                    ->relationship('destino', 'nombre')->required()->searchable()->preload()
                    ->different('origen_id'),
                Select::make('motivo')->options([
                    'reposicion' => 'Reposición',
                    'averia' => 'Avería / Reparar',
                    'correccion' => 'Corrección de ubicación',
                    'prestamo' => 'Préstamo',
                    'otro' => 'Otro',
                ]),
                DatePicker::make('fecha_solicitud')->native(false)->default(now())->required(),
                Textarea::make('observaciones')->columnSpanFull(),
            ])->columns(2),

            Section::make('Ítems a trasladar')->schema([
                Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Select::make('variante_id')
                            ->label('Variante')
                            ->relationship('variante', 'codigo_barras')
                            ->searchable()->required()->preload(),
                        TextInput::make('cantidad_solicitada')->numeric()->required()->minValue(1),
                        TextInput::make('notas'),
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
                TextColumn::make('origen.nombre')->badge()->color('info'),
                TextColumn::make('destino.nombre')->badge()->color('warning'),
                TextColumn::make('motivo')->toggleable(),
                TextColumn::make('fecha_solicitud')->date('Y-m-d')->sortable(),
                BadgeColumn::make('estado')
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoTraslado ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoTraslado ? $state->color() : 'gray'),
                TextColumn::make('items_count')->counts('items')->badge()->label('Ítems'),
                TextColumn::make('ejecutor.name')->label('Ejecutado por')->toggleable(),
            ])
            ->defaultSort('fecha_solicitud', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->options(collect(EstadoTraslado::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),
                SelectFilter::make('origen_id')->relationship('origen', 'nombre'),
                SelectFilter::make('destino_id')->relationship('destino', 'nombre'),
            ])
            ->recordActions([
                Action::make('ejecutar')
                    ->label('Ejecutar traslado')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Se moverá el stock del origen al destino y se registrarán los movimientos de inventario. La acción se puede reversar creando un traslado inverso.')
                    ->visible(fn (Traslado $r) => $r->estado === EstadoTraslado::Borrador)
                    ->action(function (Traslado $record) {
                        try {
                            EjecutarTraslado::run($record);
                            Notification::make()->title('Traslado ejecutado')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('No se pudo ejecutar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make()->visible(fn (Traslado $r) => $r->estado === EstadoTraslado::Borrador),
                DeleteAction::make()->visible(fn (Traslado $r) => $r->estado === EstadoTraslado::Borrador),
            ])
            ->emptyStateHeading('Aún no hay traslados')
            ->emptyStateDescription('Los traslados mueven mercancía entre tus bodegas sin cambiar el costo. Útiles para reponer avería, prestar entre puntos o corregir ubicación.')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTraslados::route('/'),
            'create' => Pages\CreateTraslado::route('/create'),
            'edit' => Pages\EditTraslado::route('/{record}/edit'),
        ];
    }
}
