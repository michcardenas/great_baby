<?php

namespace App\Modules\Compras\Filament\Widgets;

use App\Modules\Compras\Models\OrdenCompra;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopProveedoresWidget extends BaseWidget
{
    protected static ?string $heading = 'Top proveedores (últimos 90 días)';

    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrdenCompra::query()
                    ->join('contactos', 'contactos.id', '=', 'compras_ordenes.proveedor_id')
                    ->selectRaw('MIN(compras_ordenes.id) as id, compras_ordenes.proveedor_id,
                        contactos.nombre_completo as proveedor_nombre,
                        COUNT(*) as total_oc,
                        SUM(compras_ordenes.total) as monto_total,
                        MAX(compras_ordenes.fecha_emision) as ultima_compra')
                    ->where('compras_ordenes.fecha_emision', '>=', now()->subDays(90))
                    ->whereNotIn('compras_ordenes.estado', ['borrador', 'anulada'])
                    ->groupBy('compras_ordenes.proveedor_id', 'contactos.nombre_completo')
                    ->orderByDesc('monto_total')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('proveedor_nombre')->label('Proveedor')->searchable(),
                TextColumn::make('total_oc')->label('OCs')->badge()->color('info'),
                TextColumn::make('monto_total')->label('Comprado')->money('COP')->alignEnd()->weight('bold'),
                TextColumn::make('ultima_compra')->label('Última')->date('Y-m-d'),
            ])
            ->paginated(false);
    }
}
