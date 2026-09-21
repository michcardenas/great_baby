<?php

namespace App\Modules\Inventario\Filament\Widgets;

use App\Modules\Inventario\Models\AlertaStockDisparada;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StockBajoWidget extends BaseWidget
{
    protected static ?string $heading = 'Alertas de stock activas';

    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AlertaStockDisparada::query()
                    // C-F-QA3 · eager-load también producto agregado para no N+1 ni celdas vacías.
                    ->with(['variante.producto', 'producto', 'ubicacion'])
                    ->where('resuelta', false)
                    ->latest()
            )
            ->columns([
                // C-F-QA3 · columnas polimórficas: variante para granular O referencia+nombre para agregado.
                TextColumn::make('codigo')
                    ->label('Código')
                    ->copyable()
                    ->getStateUsing(fn ($record) => $record->esAgregada()
                        ? ($record->producto?->referencia ?? '—')
                        : ($record->variante?->codigo_barras ?? '—')),
                TextColumn::make('producto_nombre')
                    ->label('Producto')
                    ->limit(35)
                    ->getStateUsing(fn ($record) => $record->esAgregada()
                        ? (($record->producto?->nombre ?? '—').' · AGREGADO')
                        : ($record->variante?->producto?->nombre ?? '—')),
                TextColumn::make('ubicacion.nombre')->badge()->color('info'),
                BadgeColumn::make('tipo')->colors([
                    'danger' => 'minimo',
                    'warning' => 'reorden',
                    'info' => 'maximo',
                ]),
                TextColumn::make('saldo_al_disparar')->label('Saldo')->alignEnd()->badge()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'warning'),
                TextColumn::make('created_at')->since()->label('Disparada'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('resolver')
                    ->label('Marcar resuelta')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->action(function (AlertaStockDisparada $record) {
                        $record->update(['resuelta' => true, 'resuelta_at' => now(), 'resuelta_por' => auth()->id()]);
                    }),
            ])
            ->emptyStateHeading('Sin alertas activas')
            ->emptyStateDescription('El stock está dentro de los rangos configurados.')
            ->paginated([10, 25]);
    }
}
