<?php

namespace App\Modules\Inventario\Filament\Pages;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\ProductoVariante;
use BackedEnum;
use Filament\Pages\Page;

class KardexVariante extends Page
{
    protected string $view = 'inventario.pages.kardex';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'Kardex por variante';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario y Logística';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'kardex';

    public string $buscarCodigo = '';
    public ?int $varianteId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->esAracely() ?? false;
    }

    public function buscar(): void
    {
        $v = ProductoVariante::where('codigo_barras', trim($this->buscarCodigo))->first();
        $this->varianteId = $v?->id;
    }

    public function variante(): ?ProductoVariante
    {
        return $this->varianteId ? ProductoVariante::with('producto')->find($this->varianteId) : null;
    }

    public function movimientos()
    {
        if (! $this->varianteId) {
            return collect();
        }

        return InventarioMovimiento::with(['ubicacion', 'user'])
            ->where('variante_id', $this->varianteId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
    }

    public function saldos(): array
    {
        if (! $this->varianteId) {
            return [];
        }

        return InventarioMovimiento::query()
            ->where('variante_id', $this->varianteId)
            ->join('inventario_ubicaciones as u', 'u.id', '=', 'inventario_movimientos.ubicacion_id')
            ->selectRaw('u.nombre as ubicacion, SUM(inventario_movimientos.cantidad) as saldo')
            ->groupBy('u.nombre')
            ->get()
            ->toArray();
    }
}
