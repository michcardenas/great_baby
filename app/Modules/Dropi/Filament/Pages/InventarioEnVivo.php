<?php

namespace App\Modules\Dropi\Filament\Pages;

use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\ProductoVariante;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class InventarioEnVivo extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Inventario en vivo';

    protected static ?string $title = 'Saldo de inventario por variante × ubicación';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 2;

    protected string $view = 'dropi.pages.inventario-en-vivo';

    public string $busqueda = '';

    public string $filtroCategoria = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();
        return $u ? ($u->esAracely() || $u->esAlistador()) : false;
    }

    /**
     * Mapa: variante_id => [ ubicacion_id => saldo, ... ]
     */
    public function getSaldos(): array
    {
        $saldos = InventarioMovimiento::query()
            ->select('variante_id', 'ubicacion_id', DB::raw('SUM(cantidad) as saldo'))
            ->groupBy('variante_id', 'ubicacion_id')
            ->having('saldo', '!=', 0)
            ->get();

        $map = [];
        foreach ($saldos as $s) {
            $map[$s->variante_id][$s->ubicacion_id] = (int) $s->saldo;
        }
        return $map;
    }

    /** @return \Illuminate\Support\Collection<int, ProductoVariante> */
    public function getVariantes()
    {
        return ProductoVariante::query()
            ->with('producto')
            ->when($this->busqueda !== '', function ($q) {
                $b = "%{$this->busqueda}%";
                $q->where(function ($qq) use ($b) {
                    $qq->where('codigo_barras', 'like', $b)
                       ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', $b)->orWhere('referencia', 'like', $b));
                });
            })
            ->orderBy('producto_id')
            ->orderBy('codigo_barras')
            ->limit(200)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, InventarioUbicacion> */
    public function getUbicaciones()
    {
        return InventarioUbicacion::query()
            ->where('activa', true)
            ->when($this->filtroCategoria !== '', fn ($q) => $q->where('categoria', $this->filtroCategoria))
            ->orderByRaw("FIELD(categoria, 'venta','reserva_proveedor','garantia','averia_reparar','averia_baja')")
            ->orderBy('codigo')
            ->get();
    }

    public function getCategorias(): array
    {
        return collect(CategoriaUbicacion::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->prepend('Todas', '')
            ->toArray();
    }
}
