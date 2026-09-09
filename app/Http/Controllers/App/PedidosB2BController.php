<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\FacturaVentaItem;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PedidosB2BController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless($r->user()?->esAracely(), 403);
                return $next($r);
            }),
        ];
    }

    public function index(Request $request): Response
    {
        $estado = (string) $request->input('estado', '');
        $pedidos = PedidoCliente::with(['contacto:id,nombre_completo,razon_social,email', 'lista:id,nombre'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('id')->paginate(30)
            ->withQueryString();

        $conteos = PedidoCliente::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')->pluck('total', 'estado')->all();

        return Inertia::render('PedidosB2B/Index', [
            'pedidos' => $pedidos->through(fn ($p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'cliente' => $p->contacto?->razon_social ?: $p->contacto?->nombre_completo,
                'email' => $p->contacto?->email,
                'lista' => $p->lista?->nombre,
                'estado' => $p->estado,
                'total' => (float) $p->total,
                'fecha' => $p->created_at?->format('Y-m-d H:i'),
                'facturado' => (bool) $p->factura_id,
            ]),
            'conteos' => $conteos,
            'estado_filtro' => $estado ?: null,
        ]);
    }

    public function show(int $pedido): Response
    {
        $p = PedidoCliente::with(['items.variante:id,codigo_barras', 'contacto', 'lista:id,nombre', 'factura:id,numero'])
            ->findOrFail($pedido);

        return Inertia::render('PedidosB2B/Show', [
            'pedido' => [
                'id' => $p->id,
                'numero' => $p->numero,
                'estado' => $p->estado,
                'subtotal' => (float) $p->subtotal,
                'iva' => (float) $p->iva,
                'total' => (float) $p->total,
                'notas_cliente' => $p->notas_cliente,
                'notas_internas' => $p->notas_internas,
                'motivo_rechazo' => $p->motivo_rechazo,
                'creado' => $p->created_at?->format('Y-m-d H:i'),
                'enviado_at' => $p->enviado_at?->format('Y-m-d H:i'),
                'aprobado_at' => $p->aprobado_at?->format('Y-m-d H:i'),
                'facturado_at' => $p->facturado_at?->format('Y-m-d H:i'),
                'factura' => $p->factura ? ['id' => $p->factura->id, 'numero' => $p->factura->numero] : null,
                'contacto' => [
                    'id' => $p->contacto->id,
                    'nombre' => $p->contacto->razon_social ?: $p->contacto->nombre_completo,
                    'email' => $p->contacto->email,
                    'telefono' => $p->contacto->telefono,
                    'ciudad' => $p->contacto->ciudad,
                ],
                'lista' => $p->lista?->nombre,
            ],
            'items' => $p->items->map(fn ($i) => [
                'sku' => $i->sku_snapshot,
                'desc' => $i->descripcion_snapshot,
                'variante_id' => $i->variante_id,
                'cantidad' => (int) $i->cantidad,
                'precio' => (float) $i->precio_unitario,
                'total' => (float) $i->total,
            ])->values(),
        ]);
    }

    public function aprobar(int $pedido): RedirectResponse
    {
        // C-QA-D-3: lock para evitar carrera Aprobar/Rechazar concurrente.
        DB::transaction(function () use ($pedido) {
            $p = PedidoCliente::whereKey($pedido)->lockForUpdate()->firstOrFail();
            abort_unless($p->estado === 'enviado', 422, 'Sólo se aprueban pedidos enviados.');
            $p->update(['estado' => 'aprobado', 'aprobado_at' => now()]);
        });
        return back()->with('success', 'Pedido aprobado. Listo para facturar.');
    }

    public function rechazar(Request $r, int $pedido): RedirectResponse
    {
        // C-QA-D-Bloque2: mín 10 chars — motivos como "no" son inútiles para el cliente.
        $data = $r->validate(['motivo' => ['required', 'string', 'min:10', 'max:500']]);
        DB::transaction(function () use ($pedido, $data) {
            $p = PedidoCliente::whereKey($pedido)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($p->estado, ['enviado', 'aprobado'], true), 422, 'Estado no permite rechazar.');
            $p->update([
                'estado' => 'rechazado',
                'rechazado_at' => now(),
                'motivo_rechazo' => $data['motivo'],
            ]);
        });
        return back()->with('success', 'Pedido rechazado.');
    }

    public function facturar(int $pedido): RedirectResponse
    {
        $factura = DB::transaction(function () use ($pedido) {
            // C-QA-D-3: lock + revalidar dentro de tx.
            $p = PedidoCliente::with('items')->whereKey($pedido)->lockForUpdate()->firstOrFail();
            abort_unless($p->estado === 'aprobado', 422, 'Sólo se factura un pedido aprobado.');
            abort_if($p->factura_id, 422, 'Pedido ya facturado.');
            abort_if($p->items->isEmpty(), 422, 'Pedido sin ítems, no se puede facturar.');
            abort_if((float) $p->total <= 0, 422, 'Pedido con total 0, no se puede facturar.');

            // C-QA-D-Bloque2: fecha vencimiento del crédito del cliente, no hardcoded 30d.
            $plazo = \App\Modules\Cartera\Models\CondicionCredito::query()
                ->where('contacto_id', $p->contacto_id)
                ->where('activa', true)
                ->latest('vigente_desde')
                ->value('plazo_dias') ?? 30;

            $f = FacturaVenta::create([
                'numero' => $this->siguienteNumeroFactura(),  // C-QA-D-2
                'contacto_id' => $p->contacto_id,
                'fecha_emision' => now()->toDateString(),
                'fecha_vencimiento' => now()->addDays($plazo)->toDateString(),
                'estado' => 'pendiente',
                'subtotal' => $p->subtotal,
                'descuento' => 0,
                'impuestos' => $p->iva,
                'total' => $p->total,
                'saldo' => $p->total,
                'es_electronica' => false,
                'origen_type' => \App\Modules\Portal\Models\PedidoCliente::class,
                'origen_id' => $p->id,
                'observaciones' => 'Pedido B2B ' . $p->numero,
            ]);

            foreach ($p->items as $it) {
                FacturaVentaItem::create([
                    'factura_id' => $f->id,
                    'variante_id' => $it->variante_id,
                    'descripcion' => $it->descripcion_snapshot,
                    'cantidad' => $it->cantidad,
                    'precio_unit' => $it->precio_unitario,
                    'descuento_pct' => 0,
                    'impuesto_pct' => $it->iva_porcentaje,
                    'subtotal' => $it->subtotal,
                ]);
            }

            // C-QA-D-1 (CRÍTICO): el observer `saved` corre en create con items=0 y NO genera asiento.
            // Disparamos explícitamente después de insertar ítems.
            // El action es idempotente (chequea si ya existe asiento para esta factura).
            (new \App\Modules\Cartera\Actions\RegistrarAsientoContable())->factura($f->refresh());

            $p->update([
                'estado' => 'facturado',
                'facturado_at' => now(),
                'facturado_por_id' => auth()->id(),
                'factura_id' => $f->id,
            ]);

            // QA-D Bloque3: actualizar CRM del contacto (ultima_compra_at + total_comprado_ytd).
            // Antes quedaba frío hasta que otro job barra. Ahora se refleja al instante.
            $contacto = \App\Models\Contacto::find($p->contacto_id);
            if ($contacto) {
                $ytdTotal = FacturaVenta::where('contacto_id', $contacto->id)
                    ->whereYear('fecha_emision', now('America/Bogota')->year)
                    ->whereNotIn('estado', ['borrador', 'anulada'])
                    ->sum('total');
                $contacto->forceFill([
                    'ultima_compra_at' => now(),
                    'total_comprado_ytd' => $ytdTotal,
                ])->saveQuietly();
            }

            return $f;
        });

        // QA-D Bloque3: invalidar cache portal del cliente (saldo, pendientes, vencidas cambian).
        \Illuminate\Support\Facades\Cache::forget("portal.kpis.{$factura->contacto_id}");

        return back()->with('success', "Facturado como {$factura->numero}.");
    }

    /**
     * Re-audit DATOS C3/C4 · consecutivo transaccional real, con validación de
     * rango DIAN de la empresa. Reemplaza el SELECT MAX+1 anterior (race +
     * ancho fijo se rompe en 10 000).
     */
    private function siguienteNumeroFactura(): string
    {
        $prefijo = (setting('empresa.prefijo_dian') ?: 'FV') . '-' . now()->format('ymd') . '-';
        $rangoHasta = (int) setting('empresa.rango_hasta', 0) ?: null;

        return \App\Modules\Cartera\Actions\SiguienteConsecutivoFactura::run(
            $prefijo,
            4,
            $rangoHasta,
        );
    }
}
