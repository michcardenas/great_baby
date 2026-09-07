<?php

namespace App\Livewire;

use App\Models\Contacto;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\ProductoVariante;
use Livewire\Component;

/**
 * Command palette global (Cmd+K / Ctrl+K).
 * Busca en 4 entidades y devuelve resultados en un dropdown.
 */
class CommandPalette extends Component
{
    public bool $abierto = false;

    public string $q = '';

    public function render()
    {
        $q = trim($this->q);
        // Escapar wildcards SQL para que %/_ del usuario no listen todo
        $qEsc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $like = "%{$qEsc}%";

        $u = auth()->user();
        $esAracely = $u?->esAracely() ?? false;
        $esAlistador = $u?->hasAnyRole(['Alistador']) ?? false;

        $resultados = [];

        if (mb_strlen($q) >= 2 && $u) {
            $facturas = collect();
            $contactos = collect();

            // Facturas y contactos: solo Aracely y roles financieros
            if ($esAracely || $u->hasAnyRole(['Gerente', 'Contador', 'Vendedor'])) {
                $facturas = FacturaVenta::where(function ($qq) use ($like) {
                    $qq->where('numero', 'like', $like)
                       ->orWhere('numero_siigo', 'like', $like);
                })
                    ->limit(5)->get()
                    ->map(fn ($f) => [
                        'tipo' => 'Factura',
                        'icono' => '🧾',
                        'titulo' => $f->numero_siigo ? "{$f->numero} · SIIGO {$f->numero_siigo}" : $f->numero,
                        'sub' => 'Total: $' . number_format((float) $f->total, 0, ',', '.') . ' · ' . ($f->contacto?->nombreDisplay() ?? '—'),
                        'url' => "/admin/facturas-venta/{$f->id}",
                    ]);

                $contactos = Contacto::where(function ($qq) use ($like) {
                        $qq->where('nombre_completo', 'like', $like)
                            ->orWhere('razon_social', 'like', $like)
                            ->orWhere('numero_documento', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    })
                    ->limit(5)->get()
                    ->map(fn ($c) => [
                        'tipo' => 'Contacto',
                        'icono' => '👤',
                        'titulo' => $c->nombreDisplay() ?? $c->nombre_completo,
                        'sub' => ($c->tipo_documento ?? '') . ' ' . ($c->numero_documento ?? '') . ' · ' . ($c->email ?? '—'),
                        'url' => "/admin/contactos/{$c->id}",
                    ]);
            }

            // Pedidos: Aracely, Alistador y roles operativos
            $pedidos = collect();
            if ($esAracely || $esAlistador) {
                $pedidos = DropiPedido::where(function ($qq) use ($like) {
                    $qq->where('guia', 'like', $like)
                       ->orWhere('cliente_nombre', 'like', $like)
                       ->orWhere('dropi_orden_id', 'like', $like);
                })
                    ->limit(5)->get()
                    ->map(fn ($p) => [
                        'tipo' => 'Pedido Dropi',
                        'icono' => '📦',
                        'titulo' => "{$p->guia} · {$p->cliente_nombre}",
                        'sub' => ($p->cliente_ciudad ?? '—') . ' · ' . ucfirst(str_replace('_', ' ', is_object($p->estado) ? $p->estado->value : $p->estado)),
                        'url' => "/admin/dropi-pedidos/{$p->id}",
                    ]);
            }

            // Productos: cualquier autenticado (info no sensible)
            $variantes = ProductoVariante::where(function ($qq) use ($like) {
                    $qq->where('codigo_barras', 'like', $like)
                       ->orWhere('color_nombre', 'like', $like);
                })
                ->orWhereHas('producto', fn ($qq) => $qq->where('nombre', 'like', $like))
                ->with('producto')->limit(5)->get()
                ->map(fn ($v) => [
                    'tipo' => 'Producto',
                    'icono' => '🏷️',
                    'titulo' => ($v->producto?->nombre ?? '—') . ' · ' . ($v->color_nombre ?? '') . ($v->talla ? " T{$v->talla}" : ''),
                    'sub' => 'Código: ' . ($v->codigo_barras ?? '—'),
                    'url' => '/admin/variantes',
                ]);

            $resultados = collect()
                ->concat($facturas)->concat($contactos)->concat($pedidos)->concat($variantes)
                ->take(15)->toArray();
        }

        return view('livewire.command-palette', compact('resultados'));
    }

    public function abrir(): void
    {
        $this->abierto = true;
        $this->q = '';
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->q = '';
    }
}
