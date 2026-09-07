<?php

namespace App\Modules\Inventario\Filament\Resources;

use App\Modules\Inventario\Actions\CerrarTomaFisica;
use App\Modules\Inventario\Actions\PrepararTomaFisica;
use App\Modules\Inventario\Enums\EstadoTomaFisica;
use App\Modules\Inventario\Filament\Resources\TomaFisicaResource\Pages;
use App\Modules\Inventario\Models\TomaFisica;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
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

class TomaFisicaResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = TomaFisica::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Toma física';

    protected static ?string $modelLabel = 'Toma física';

    protected static ?string $pluralModelLabel = 'Tomas físicas';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario y Logística';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'tomas-fisicas';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos')->schema([
                TextInput::make('numero')->disabled()->dehydrated(false)
                    ->placeholder('TF-YYYY-NNNNNN'),
                Select::make('ubicacion_id')->label('Bodega')
                    ->relationship('ubicacion', 'nombre')->required()->searchable()->preload(),
                Select::make('tipo')->options([
                    'total' => 'Total (toda la bodega)',
                    'parcial' => 'Parcial (por alcance)',
                    'ciclico' => 'Cíclico (solo con saldo)',
                ])->default('total')->required(),
                TextInput::make('alcance')->helperText('Ej: marca:5 · categoria:12 (opcional)'),
                DatePicker::make('fecha_conteo')->native(false)->default(now())->required(),
                Textarea::make('observaciones')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->searchable()->weight('bold')->copyable(),
                TextColumn::make('ubicacion.nombre')->badge()->color('info'),
                TextColumn::make('tipo')->badge()->toggleable(),
                TextColumn::make('fecha_conteo')->date('Y-m-d')->sortable(),
                BadgeColumn::make('estado')
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoTomaFisica ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoTomaFisica ? $state->color() : 'gray'),
                TextColumn::make('items_diferentes')->label('Con diferencia')->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('valor_ajuste')->money('COP')->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
            ])
            ->defaultSort('fecha_conteo', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->options(collect(EstadoTomaFisica::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),
                SelectFilter::make('ubicacion_id')->relationship('ubicacion', 'nombre'),
            ])
            ->recordActions([
                Action::make('iniciar_conteo')
                    ->label('Iniciar conteo')
                    ->icon('heroicon-o-play-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Se congela el saldo del sistema para todas las variantes. Al aceptar te llevo directo a la pantalla de captura para que empieces a escanear y contar.')
                    ->visible(fn (TomaFisica $r) => $r->estado === EstadoTomaFisica::Borrador)
                    ->action(function (TomaFisica $record) {
                        PrepararTomaFisica::run($record);
                        Notification::make()->title('Conteo preparado — abriendo captura')
                            ->body("{$record->items()->count()} variantes listas para contar.")
                            ->success()->send();

                        return redirect()->to("/admin/conteo-fisico/{$record->id}");
                    }),
                Action::make('continuar_conteo')
                    ->label('Continuar contando')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('warning')
                    ->url(fn (TomaFisica $r) => "/admin/conteo-fisico/{$r->id}")
                    ->visible(fn (TomaFisica $r) => $r->estado === EstadoTomaFisica::EnConteo),
                Action::make('cerrar')
                    ->label('Cerrar y ajustar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Genera movimientos de ajuste en el kardex y los asientos contables por las diferencias detectadas.')
                    ->visible(fn (TomaFisica $r) => $r->estado === EstadoTomaFisica::EnConteo)
                    ->action(function (TomaFisica $record) {
                        try {
                            CerrarTomaFisica::run($record);
                            Notification::make()->title('Toma cerrada')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make()->visible(fn (TomaFisica $r) => $r->estado === EstadoTomaFisica::Borrador),
                DeleteAction::make()->visible(fn (TomaFisica $r) => $r->estado === EstadoTomaFisica::Borrador),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTomasFisicas::route('/'),
            'create' => Pages\CreateTomaFisica::route('/create'),
            'edit' => Pages\EditTomaFisica::route('/{record}/edit'),
        ];
    }
}
