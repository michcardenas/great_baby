<?php

namespace App\Modules\Cartera\Actions;

use App\Models\Contacto;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\FacturaVentaItem;
use App\Modules\Dropi\Models\DropiCorte;
use App\Modules\Dropi\Models\DropiPedido;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * §21 Diseño Dropi — Facturación híbrida B2B/consumidor final.
 * Al cerrar el corte, los pedidos con requiere_factura_b2b=true se agrupan por vendedor
 * y se emite UNA factura B2B por vendedor con los pedidos del corte.
 *
 * @return array{facturas_creadas:int, valor_total:float, vendedores:int}
 */
class GenerarFacturasB2BDeCorte
{
    use AsAction;

    public function handle(int $corteId): array
    {
        return DB::transaction(function () use ($corteId) {
            $corte = DropiCorte::findOrFail($corteId);

            $pedidos = DropiPedido::query()
                ->where('corte_id', $corteId)
                ->where('requiere_factura_b2b', true)
                ->whereIn('estado', ['despachado', 'entregado', 'pagado'])
                ->with('items')
                ->get()
                ->groupBy('vendedor_identificacion');

            $facturasCreadas = 0;
            $valorTotal = 0.0;

            foreach ($pedidos as $vendedorDoc => $pedidosVendedor) {
                if (! $vendedorDoc) continue;
                $primero = $pedidosVendedor->first();

                // Buscar o crear contacto vendedor Dropi como cliente B2B
                $contacto = Contacto::updateOrCreate(
                    ['numero_documento' => $vendedorDoc],
                    [
                        'tipo_documento' => 'CC',
                        'nombre_completo' => $primero->vendedor_nombre ?? 'Vendedor Dropi ' . $vendedorDoc,
                        'es_vendedor_dropi' => true,
                        'es_cliente_b2b' => true,
                        'es_cliente' => true,
                        'activo' => true,
                    ]
                );

                $total = (float) $pedidosVendedor->sum('monto_esperado_proveedor');
                if ($total <= 0) continue;

                $numero = 'FV-DP-' . $corte->fecha->format('ymd') . '-' . $corte->numero . '-' . str_pad((string) $contacto->id, 4, '0', STR_PAD_LEFT);

                // Idempotente: si ya existe la factura del corte para ese vendedor, saltar
                if (FacturaVenta::where('numero', $numero)->exists()) continue;

                $factura = FacturaVenta::create([
                    'numero' => $numero,
                    'contacto_id' => $contacto->id,
                    'fecha_emision' => $corte->cerrado_at?->toDateString() ?? now()->toDateString(),
                    'fecha_vencimiento' => (clone ($corte->cerrado_at ?? now()))->addDays(30)->toDateString(),
                    'estado' => 'pendiente',
                    'subtotal' => $total,
                    'total' => $total,
                    'saldo' => $total,
                    'origen_type' => DropiCorte::class,
                    'origen_id' => $corte->id,
                    'observaciones' => "Facturación B2B del corte " . $corte->etiqueta() . " · " . $pedidosVendedor->count() . " pedidos",
                ]);

                foreach ($pedidosVendedor as $p) {
                    FacturaVentaItem::create([
                        'factura_id' => $factura->id,
                        'descripcion' => "Guía {$p->guia} · " . ($p->cliente_nombre ?? '—'),
                        'cantidad' => 1,
                        'precio_unit' => (float) $p->monto_esperado_proveedor,
                        'subtotal' => (float) $p->monto_esperado_proveedor,
                    ]);
                }

                $facturasCreadas++;
                $valorTotal += $total;
            }

            return [
                'facturas_creadas' => $facturasCreadas,
                'valor_total' => $valorTotal,
                'vendedores' => $facturasCreadas,
            ];
        });
    }
}
