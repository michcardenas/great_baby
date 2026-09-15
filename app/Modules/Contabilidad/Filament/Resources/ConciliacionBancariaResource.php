<?php

namespace App\Modules\Contabilidad\Filament\Resources;

use App\Modules\Contabilidad\Filament\Resources\ConciliacionBancariaResource\Pages;
use App\Modules\Contabilidad\Models\ConciliacionBancaria;
use App\Modules\Siigo\Enums\TipoExportacionSiigo;
use App\Modules\Siigo\Services\SiigoExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Conciliación bancaria — módulo CONTABILIDAD (dominio de Silvia).
 * Se exporta a SIIGO como asiento en modo BORRADOR (los valores se ajustan allá).
 */
class ConciliacionBancariaResource extends Resource
{
    protected static ?string $model = ConciliacionBancaria::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Conciliación bancaria';

    protected static ?string $modelLabel = 'Conciliación bancaria';

    protected static ?string $pluralModelLabel = 'Conciliaciones bancarias';

    protected static string|\UnitEnum|null $navigationGroup = 'Contabilidad';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'conciliacion-bancaria';

    public static function canViewAny(): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Conciliación del día')
                ->description('Cuenta bancaria, fecha y saldos a comparar')
                ->icon('heroicon-o-scale')
                ->columns(2)
                ->schema([
                    DatePicker::make('fecha')->label('Fecha')->default(now())->required()
                        ->native(false)->displayFormat('d/m/Y'),
                    TextInput::make('banco')->label('Banco / cuenta')->required()->maxLength(120)
                        ->placeholder('Ej: Bancolombia — Ahorros 1234'),
                    TextInput::make('cuenta_puc')->label('Cuenta PUC')->default('1110')->maxLength(20)
                        ->placeholder('1110'),
                    Select::make('estado')->label('Estado')->native(false)->required()->default('pendiente')
                        ->options([
                            'pendiente' => 'Pendiente',
                            'conciliada' => 'Conciliada',
                            'exportada' => 'Exportada',
                        ]),
                    TextInput::make('saldo_extracto')->label('Saldo extracto (banco)')->numeric()->prefix('$')
                        ->required()->placeholder('0'),
                    TextInput::make('saldo_sistema')->label('Saldo sistema')->numeric()->prefix('$')
                        ->required()->placeholder('0'),
                    Textarea::make('notas')->label('Notas')->rows(2)->columnSpanFull()
                        ->helperText('La diferencia (extracto − sistema) se calcula automáticamente al guardar.'),
                ]),

            Section::make('Soporte')
                ->description('Adjunta el extracto o cualquier archivo de respaldo (PDF, Excel, imagen…)')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    FileUpload::make('adjuntos')
                        ->label('Archivos de soporte')
                        ->multiple()
                        ->disk('public')
                        ->directory('conciliaciones')
                        ->downloadable()
                        ->openable()
                        ->reorderable()
                        ->maxSize(10240)
                        ->helperText('Cualquier tipo de archivo, hasta 10 MB cada uno.')
                        ->columnSpanFull(),
                ]),

            // Quién registra la conciliación — usuario en sesión.
            Hidden::make('user_id')->default(fn () => auth()->id()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('banco')->searchable(),
                TextColumn::make('cuenta_puc')->label('Cuenta'),
                TextColumn::make('saldo_extracto')->money('COP')->label('Extracto'),
                TextColumn::make('saldo_sistema')->money('COP')->label('Sistema'),
                TextColumn::make('diferencia')->money('COP')
                    ->color(fn ($state) => abs((float) $state) < 0.01 ? 'success' : 'danger'),
                TextColumn::make('estado')->badge()->color(fn ($state) => match ($state) {
                    'exportada' => 'success',
                    'conciliada' => 'info',
                    default => 'gray',
                }),
                TextColumn::make('siigo_borrador')->label('SIIGO')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->siigo_exportado_at ? ($state ? 'Borrador' : 'Enviado') : '—')
                    ->color(fn ($record) => $record->siigo_exportado_at ? 'warning' : 'gray'),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Fecha desde'),
                        DatePicker::make('hasta')->label('Fecha hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
                        ->when($data['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v)))
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if ($data['desde'] ?? null) { $i[] = 'Desde '.$data['desde']; }
                        if ($data['hasta'] ?? null) { $i[] = 'Hasta '.$data['hasta']; }
                        return $i;
                    }),
                SelectFilter::make('banco')
                    ->label('Banco')
                    ->options(fn () => ConciliacionBancaria::query()->whereNotNull('banco')
                        ->distinct()->orderBy('banco')->pluck('banco', 'banco')->all()),
                SelectFilter::make('estado')->options([
                    'pendiente' => 'Pendiente',
                    'conciliada' => 'Conciliada',
                    'exportada' => 'Exportada',
                ]),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('enviar_siigo')
                    ->label('Enviar a SIIGO')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Se envía la conciliación a SIIGO como asiento en modo BORRADOR, para que contabilidad ajuste los valores allá.')
                    ->action(function (ConciliacionBancaria $record) {
                        try {
                            $res = app(SiigoExportService::class)->exportar(TipoExportacionSiigo::Conciliacion, $record);
                            Notification::make()->title('Conciliación enviada a SIIGO')
                                ->body(($res['borrador'] ? 'Quedó como BORRADOR para ajustar en SIIGO. ' : 'Enviada. ')
                                    . 'Nº SIIGO: ' . ($res['siigo_numero'] ?? $res['siigo_id'] ?? '—'))
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('No se pudo enviar a SIIGO')
                                ->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        // Solo index: crear y editar en MODAL.
        return [
            'index' => Pages\ListConciliaciones::route('/'),
        ];
    }
}
