<?php

namespace App\Modules\Cartera\Filament\Resources;

use App\Modules\Cartera\Filament\Resources\MovimientoContableResource\Pages;
use App\Modules\Cartera\Models\MovimientoContable;
use BackedEnum;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MovimientoContableResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = MovimientoContable::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Movimientos contables';

    protected static ?string $modelLabel = 'Movimiento';

    protected static ?string $pluralModelLabel = 'Libro diario';

    // Libro diario / movimientos contables — pertenece a Contabilidad (dominio de Silvia),
    // no a Cartera. Se reubica en el grupo Contabilidad.
    protected static string|\UnitEnum|null $navigationGroup = 'Contabilidad';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'libro-diario';

    public static function canViewAny(): bool
    {
        return \App\Auth\Permisos::puede(auth()->user(), 'libro_diario');
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->date('Y-m-d')->sortable(),
                TextColumn::make('cuenta_puc')->label('Cuenta')->badge()->color('gray')->searchable(),
                TextColumn::make('descripcion')->limit(45)->tooltip(fn ($record) => $record->descripcion)->searchable(),
                TextColumn::make('debe')->money('COP')->alignEnd()->color('success')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '$' . number_format((float) $state, 0, ',', '.') : ''),
                TextColumn::make('haber')->money('COP')->alignEnd()->color('danger')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '$' . number_format((float) $state, 0, ',', '.') : ''),
                TextColumn::make('origen_type')
                    ->label('Origen')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()->color('info')->toggleable(),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                Filter::make('rango')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('desde')->label('Fecha desde')->native(false)->displayFormat('d/m/Y'),
                        \Filament\Forms\Components\DatePicker::make('hasta')->label('Fecha hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
                        ->when($data['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v)))
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if ($data['desde'] ?? null) { $i[] = 'Desde '.$data['desde']; }
                        if ($data['hasta'] ?? null) { $i[] = 'Hasta '.$data['hasta']; }
                        return $i;
                    }),
                SelectFilter::make('cuenta_puc')
                    ->label('Cuenta PUC')
                    ->searchable()
                    ->options(fn () => MovimientoContable::query()->whereNotNull('cuenta_puc')
                        ->distinct()->orderBy('cuenta_puc')->pluck('cuenta_puc', 'cuenta_puc')->toArray()),
                SelectFilter::make('tipo')
                    ->label('Tipo de movimiento')
                    ->options(['debe' => 'Débitos (Debe)', 'haber' => 'Créditos (Haber)'])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(($data['value'] ?? null) === 'debe', fn ($q) => $q->where('debe', '>', 0))
                        ->when(($data['value'] ?? null) === 'haber', fn ($q) => $q->where('haber', '>', 0))),
                SelectFilter::make('origen_type')
                    ->label('Origen')
                    ->options(fn () => MovimientoContable::query()->whereNotNull('origen_type')
                        ->distinct()->pluck('origen_type')
                        ->mapWithKeys(fn ($t) => [$t => class_basename($t)])->toArray()),
                SelectFilter::make('canal')
                    ->label('Canal')
                    ->options(['empresa' => 'Empresa', 'dropi' => 'Dropi'])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(($data['value'] ?? null) === 'dropi', fn ($q) => $q->where('origen_type', 'like', '%Dropi%'))
                        ->when(($data['value'] ?? null) === 'empresa', fn ($q) => $q->where(
                            fn ($w) => $w->whereNull('origen_type')->orWhere('origen_type', 'not like', '%Dropi%')
                        ))),
            ])
            // Filtros visibles siempre, arriba de la tabla (no escondidos en el icono).
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('libro_diario')
                                ->fromTable()->withFilename('libro-diario-' . now()->format('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMovimientos::route('/')];
    }
}
