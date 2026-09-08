<?php

namespace App\Modules\Crm\Filament\Resources;

use App\Modules\Crm\Filament\Resources\InteraccionResource\Pages;
use App\Modules\Crm\Models\Interaccion;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InteraccionResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = Interaccion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Interacciones CRM';

    protected static ?string $modelLabel = 'Interacción';

    protected static ?string $pluralModelLabel = 'Interacciones CRM';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'crm-interacciones';

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        if (! $u) return false;
        return $u->esAracely() || $u->hasAnyRole(['Gerente', 'Vendedor']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Interacción')->schema([
                Select::make('contacto_id')
                    ->label('Cliente')
                    ->relationship('contacto', 'nombre_completo')
                    ->searchable()->preload()->required(),
                Select::make('tipo')
                    ->options(Interaccion::tipos())
                    ->required(),
                TextInput::make('asunto')->required()->maxLength(200),
                Textarea::make('detalle')->rows(4)->columnSpanFull(),
                DateTimePicker::make('ocurrida_at')->label('Ocurrida el')
                    ->default(now())->required()->native(false),
                Select::make('resultado')->options(Interaccion::resultados()),
            ])->columns(2),

            Section::make('Próxima acción (opcional)')->schema([
                DateTimePicker::make('proxima_accion_at')->label('Fecha')->native(false),
                Textarea::make('proxima_accion_nota')->label('Nota')->rows(2),
            ])->columns(2)->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ocurrida_at')->date('Y-m-d H:i')->sortable(),
                BadgeColumn::make('tipo')
                    ->formatStateUsing(fn ($state) => Interaccion::tipos()[$state] ?? $state),
                TextColumn::make('contacto.nombre_completo')->label('Cliente')->searchable()->limit(28),
                TextColumn::make('asunto')->searchable()->limit(45),
                TextColumn::make('usuario.name')->label('Por')->limit(18),
                BadgeColumn::make('resultado')
                    ->formatStateUsing(fn ($state) => $state ? (Interaccion::resultados()[$state] ?? $state) : '—')
                    ->color(fn ($state) => match ($state) {
                        'exitoso' => 'success',
                        'sin_respuesta' => 'warning',
                        'reagendar' => 'info',
                        'cerrado' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('proxima_accion_at')->label('Próx.')->date('Y-m-d')
                    ->color(fn ($state) => $state && $state->lt(now()) ? 'danger' : 'gray'),
            ])
            ->defaultSort('ocurrida_at', 'desc')
            ->filters([
                SelectFilter::make('tipo')->options(Interaccion::tipos()),
                SelectFilter::make('resultado')->options(Interaccion::resultados()),
                SelectFilter::make('contacto_id')->relationship('contacto', 'nombre_completo')
                    ->searchable()->preload()->label('Cliente'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInteracciones::route('/'),
            'create' => Pages\CreateInteraccion::route('/create'),
            'edit' => Pages\EditInteraccion::route('/{record}/edit'),
        ];
    }
}
