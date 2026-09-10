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

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 8;

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
                TextColumn::make('cuenta_puc')->label('Cuenta')->badge()->color('gray'),
                TextColumn::make('descripcion')->limit(45)->tooltip(fn ($r) => $r->descripcion),
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
                SelectFilter::make('cuenta_puc')
                    ->options(fn () => MovimientoContable::query()->distinct()->pluck('cuenta_puc', 'cuenta_puc')->toArray()),
                Filter::make('rango')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('desde')->native(false),
                        \Filament\Forms\Components\DatePicker::make('hasta')->native(false),
                    ])
                    ->query(fn (Builder $q, array $d) => $q
                        ->when($d['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
                        ->when($d['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))),
            ])
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
