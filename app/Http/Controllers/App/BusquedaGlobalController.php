<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * MEJORAS-A · ⌘K: búsqueda global instantánea desde cualquier página.
 * Busca en facturas, contactos, pedidos Dropi, pedidos B2B, variantes por código.
 */
class BusquedaGlobalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(['auth', 'throttle:60,1'])];
    }

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['resultados' => []]);
        }

        $out = [];
        $like = "%{$q}%";

        // Facturas por número
        foreach (FacturaVenta::where('numero', 'like', $like)->limit(5)->get(['id', 'numero', 'total', 'estado']) as $f) {
            $out[] = [
                'tipo' => 'Factura',
                'label' => $f->numero,
                'sub' => '$' . number_format($f->total, 0, ',', '.') . ' · ' . (is_object($f->estado) ? $f->estado->value : $f->estado),
                'url' => '/app/facturas/' . $f->id,
                'icon' => 'FileText',
            ];
        }

        // Contactos
        foreach (Contacto::where('activo', true)->where(function ($w) use ($like) {
            $w->where('nombre_completo', 'like', $like)
                ->orWhere('razon_social', 'like', $like)
                ->orWhere('numero_documento', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('telefono', 'like', $like);
        })->limit(5)->get(['id', 'nombre_completo', 'razon_social', 'telefono']) as $c) {
            $out[] = [
                'tipo' => 'Contacto',
                'label' => $c->razon_social ?: $c->nombre_completo,
                'sub' => $c->telefono ?? '',
                'url' => '/app/contactos/' . $c->id,
                'icon' => 'User',
            ];
        }

        // Pedidos Dropi por guía o Dropi ID
        foreach (DropiPedido::where(function ($w) use ($like) {
            $w->where('guia', 'like', $like)->orWhere('dropi_orden_id', 'like', $like);
        })->limit(5)->get(['id', 'guia', 'cliente_nombre', 'estado']) as $p) {
            $out[] = [
                'tipo' => 'Dropi',
                'label' => $p->guia,
                'sub' => $p->cliente_nombre . ' · ' . (is_object($p->estado) ? $p->estado->value : $p->estado),
                'url' => '/app/dropi',
                'icon' => 'Truck',
            ];
        }

        // Pedidos B2B
        foreach (PedidoCliente::where('numero', 'like', $like)->limit(5)->get(['id', 'numero', 'total', 'estado']) as $p) {
            $out[] = [
                'tipo' => 'Pedido B2B',
                'label' => $p->numero,
                'sub' => '$' . number_format($p->total, 0, ',', '.') . ' · ' . $p->estado,
                'url' => '/app/pedidos-b2b/' . $p->id,
                'icon' => 'Package',
            ];
        }

        // Variantes por código de barras
        foreach (ProductoVariante::with('producto:id,nombre,referencia')
            ->where('codigo_barras', 'like', $like)->limit(5)->get(['id', 'producto_id', 'codigo_barras']) as $v) {
            $out[] = [
                'tipo' => 'Producto',
                'label' => $v->codigo_barras,
                'sub' => $v->producto?->nombre,
                'url' => '/app/inventario/kardex?codigo=' . urlencode($v->codigo_barras),
                'icon' => 'Boxes',
            ];
        }

        // Accesos rápidos siempre visibles cuando coincide texto
        $shortcuts = [
            ['nombre' => 'Nueva OC', 'url' => '/app/compras/oc/nueva', 'kw' => ['oc', 'compra', 'orden']],
            ['nombre' => 'Registrar devolución Dropi', 'url' => '/app/dropi/devolucion/registrar', 'kw' => ['devol', 'dropi']],
            ['nombre' => 'Escáner cámara', 'url' => '/app/dropi/escaner-camara', 'kw' => ['escan', 'camara']],
            ['nombre' => 'Nueva garantía', 'url' => '/app/garantias/nueva', 'kw' => ['garan', 'ticket']],
            ['nombre' => 'Estación de empaque', 'url' => '/app/estacion-empaque', 'kw' => ['empaque', 'estacion']],
            ['nombre' => 'Torre de Control', 'url' => '/app', 'kw' => ['torre', 'control']],
        ];
        $qLower = mb_strtolower($q);
        foreach ($shortcuts as $s) {
            foreach ($s['kw'] as $kw) {
                if (str_contains($qLower, $kw)) {
                    $out[] = ['tipo' => 'Acción', 'label' => $s['nombre'], 'sub' => '', 'url' => $s['url'], 'icon' => 'Zap'];
                    break;
                }
            }
        }

        return response()->json(['resultados' => array_slice($out, 0, 20)]);
    }
}
