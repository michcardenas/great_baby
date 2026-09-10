<?php

namespace App\Modules\Cartera\Filament\Resources;

use App\Modules\Cartera\Enums\ClasificacionDiferencia;
use App\Modules\Cartera\Filament\Resources\PagoVentaResource\Pages;
use App\Modules\Cartera\Models\PagoVenta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use App\Support\FilamentPolicy\HeredaAutorizacion;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagoVentaResource extends Resource
{
    use HeredaAutorizacion;

    protected static ?string $model = PagoVenta::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Pagos';

    protected static ?string $modelLabel = 'Pago';

    protected static ?string $pluralModelLabel = 'Pagos recibidos';

    protected static string|\UnitEnum|null $navigationGroup = 'Cartera y CRM';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'pagos-venta';

    public static function canViewAny(): bool
    {
        return \App\Auth\Permisos::puede(auth()->user(), 'pagos');
    }

    public static function canCreate(): bool { return false; } // se crean desde Factura → Registrar pago

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->date('Y-m-d')->sortable(),
                TextColumn::make('factura.numero')->label('Factura')->searchable()->weight('bold'),
                TextColumn::make('contacto.nombre_completo')->label('Cliente')->limit(28)->searchable(),
                TextColumn::make('medio_pago')->badge()->color('gray'),
                TextColumn::make('monto_recibido')->money('COP')->alignEnd()->sortable(),
                TextColumn::make('monto_aplicado')->money('COP')->alignEnd(),
                TextColumn::make('diferencia')->money('COP')->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : ($state < 0 ? 'info' : 'success')),
                BadgeColumn::make('clasificacion_diferencia')
                    ->label('Clasificación')
                    ->formatStateUsing(fn ($state) => $state instanceof ClasificacionDiferencia ? $state->label() : ($state ?? '—'))
                    ->color(fn ($state) => $state instanceof ClasificacionDiferencia ? $state->color() : 'gray'),
                TextColumn::make('referencia')->toggleable(),
                TextColumn::make('registrador.name')->label('Registró')->toggleable(),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                SelectFilter::make('clasificacion_diferencia')
                    ->options(collect(ClasificacionDiferencia::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                SelectFilter::make('medio_pago')->options([
                    'transferencia' => 'Transferencia', 'efectivo' => 'Efectivo',
                    'tarjeta' => 'Tarjeta', 'nequi' => 'Nequi', 'daviplata' => 'Daviplata', 'otro' => 'Otro',
                ]),
            ])
            ->headerActions([
                Action::make('importar_extracto')
                    ->label('Importar extracto bancario')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->modalHeading('Importar extracto bancario y conciliar automáticamente')
                    ->modalContent(view('cartera.modals.importar-extracto'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                        ->exports([
                            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('pagos')
                                ->fromTable()->withFilename('pagos-' . now()->format('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPagosVenta::route('/')];
    }
}
