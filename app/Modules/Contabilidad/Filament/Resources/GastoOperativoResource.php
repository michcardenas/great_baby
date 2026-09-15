<?php

namespace App\Modules\Contabilidad\Filament\Resources;

use App\Modules\Contabilidad\Filament\Resources\GastoOperativoResource\Pages;
use App\Modules\Gerencia\Models\GastoOperativo;
use App\Modules\Siigo\Enums\TipoExportacionSiigo;
use App\Modules\Siigo\Services\SiigoExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
 * Gastos operativos — vive en el módulo CONTABILIDAD (dominio de Silvia).
 * Incluye la acción "Enviar a SIIGO" (asiento contable, migración directa).
 */
class GastoOperativoResource extends Resource
{
    protected static ?string $model = GastoOperativo::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Gastos';

    protected static ?string $modelLabel = 'Gasto';

    protected static ?string $pluralModelLabel = 'Gastos';

    protected static string|\UnitEnum|null $navigationGroup = 'Contabilidad';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'gastos';

    // Acceso: solo el ámbito contable (Aracely / Gerencia / Gerente / Contador = Silvia).
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
            Section::make('Datos del gasto')->schema([
                TextInput::make('numero')->label('N°')->maxLength(50),
                DatePicker::make('fecha')->default(now())->required(),
                TextInput::make('categoria')->maxLength(100)
                    ->helperText('Ej: Arriendo, Servicios, Papelería…'),
                TextInput::make('descripcion')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('monto')->numeric()->prefix('$')->required(),
                TextInput::make('proveedor')->maxLength(150),
                TextInput::make('factura_ref')->label('Factura / soporte')->maxLength(100),
                Select::make('metodo_pago')->label('Método de pago')->options([
                    'transferencia' => 'Transferencia',
                    'efectivo' => 'Efectivo',
                    'tarjeta' => 'Tarjeta',
                    'nequi' => 'Nequi',
                    'daviplata' => 'Daviplata',
                ]),
                Select::make('estado')->options([
                    'pendiente' => 'Pendiente',
                    'aprobado' => 'Aprobado',
                    'pagado' => 'Pagado',
                ])->default('pendiente'),
                Textarea::make('notas')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('N°')->searchable(),
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('categoria')->searchable(),
                TextColumn::make('descripcion')->limit(40)->searchable(),
                TextColumn::make('monto')->money('COP')->sortable(),
                TextColumn::make('proveedor')->searchable()->toggleable(),
                TextColumn::make('estado')->badge()->color(fn ($state) => match ($state) {
                    'pagado' => 'success',
                    'aprobado' => 'info',
                    default => 'gray',
                }),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Fecha desde'),
                        DatePicker::make('hasta')->label('Fecha hasta'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['desde'] ?? null, fn (Builder $q, $d) => $q->whereDate('fecha', '>=', $d))
                        ->when($data['hasta'] ?? null, fn (Builder $q, $d) => $q->whereDate('fecha', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if ($data['desde'] ?? null) { $i[] = 'Desde '.$data['desde']; }
                        if ($data['hasta'] ?? null) { $i[] = 'Hasta '.$data['hasta']; }
                        return $i;
                    }),
                SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->options(fn () => GastoOperativo::query()->whereNotNull('categoria')
                        ->distinct()->orderBy('categoria')->pluck('categoria', 'categoria')->all()),
                SelectFilter::make('metodo_pago')->label('Método de pago')->options([
                    'transferencia' => 'Transferencia',
                    'efectivo' => 'Efectivo',
                    'tarjeta' => 'Tarjeta',
                    'nequi' => 'Nequi',
                    'daviplata' => 'Daviplata',
                ]),
                SelectFilter::make('estado')->options([
                    'pendiente' => 'Pendiente',
                    'aprobado' => 'Aprobado',
                    'pagado' => 'Pagado',
                ]),
            ])
            ->recordActions([
                Action::make('enviar_siigo')
                    ->label('Enviar a SIIGO')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Se envía el gasto a SIIGO como asiento contable (migración directa, no borrador).')
                    ->action(function (GastoOperativo $record) {
                        try {
                            $res = app(SiigoExportService::class)->exportar(TipoExportacionSiigo::Gasto, $record);
                            Notification::make()->title('Gasto enviado a SIIGO')
                                ->body('Nº SIIGO: ' . ($res['siigo_numero'] ?? $res['siigo_id'] ?? '—'))
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
        return [
            'index' => Pages\ListGastos::route('/'),
            'create' => Pages\CreateGasto::route('/create'),
            'edit' => Pages\EditGasto::route('/{record}/edit'),
        ];
    }
}
