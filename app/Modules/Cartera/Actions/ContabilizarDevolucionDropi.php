<?php

namespace App\Modules\Cartera\Actions;

use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Dropi\Models\DropiDevolucion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Al registrar una devolución Dropi, escribe el asiento inverso BALANCEADO
 * (partida doble: ΣDébitos = ΣHaberes exactamente):
 *
 *   4175 (Devoluciones en ventas)   DÉBITO  = valor (por-item)
 *   1305 (Cuentas por cobrar / CxC) HABER   = valor total    ← CONTRAPARTIDA
 *   1435 (Inventario)                DÉBITO  = valor (si reingresa)
 *   6135 (Costo mercancía vendida)   HABER   = valor (si reingresa)
 *
 * Multi-producto: cada ítem usa la cta contable de su Producto (o el default de Reglas).
 * Prorrateo del valor total ítem-por-ítem con ajuste de residuo en el ÚLTIMO ítem
 * para eliminar dispersión de centavos por round().
 */
class ContabilizarDevolucionDropi
{
    use AsAction;

    public function handle(DropiDevolucion $devolucion): int
    {
        $devolucion->loadMissing('pedido.items.variante.producto');
        $pedido = $devolucion->pedido;
        if (! $pedido) return 0;

        $valor = (float) $pedido->monto_esperado_proveedor;
        if ($valor <= 0) {
            $devolucion->update([
                'notas' => trim(($devolucion->notas ?? '') . "\n[Contab skip] pedido con monto=0, sin asiento"),
            ]);
            \Illuminate\Support\Facades\Log::info('[Contab-Dev] skip monto=0', ['pedido' => $pedido->guia]);
            return 0;
        }

        if (MovimientoContable::where('origen_type', DropiDevolucion::class)
            ->where('origen_id', $devolucion->id)->exists()) {
            return 0;
        }

        $fecha = $devolucion->recibido_at?->toDateString() ?? now()->toDateString();
        $userId = auth()->id();

        // ---------- Prorrateo con ajuste de residuo ----------
        $items = $pedido->items->filter(fn ($it) => (float) ($it->cantidad ?? 1) > 0)->values();
        $totalPeso = (float) $items->sum(fn ($it) => (float) ($it->cantidad ?? 1) * (float) ($it->precio_unitario ?? 0));
        if ($totalPeso <= 0) {
            $totalPeso = (float) $items->sum(fn ($it) => (float) ($it->cantidad ?? 1));
            $pesoItem = fn ($it) => (float) ($it->cantidad ?? 1);
        } else {
            $pesoItem = fn ($it) => (float) ($it->cantidad ?? 1) * (float) ($it->precio_unitario ?? 0);
        }

        // Pre-calcular porciones con ajuste de residuo en el último ítem.
        $porciones = [];
        $acumulado = 0.0;
        $n = $items->count();
        foreach ($items as $idx => $item) {
            $peso = $pesoItem($item);
            if ($peso <= 0) { $porciones[$idx] = 0.0; continue; }
            $esUltimo = ($idx === $n - 1);
            $porciones[$idx] = $esUltimo
                ? round($valor - $acumulado, 2)  // absorbe residuo
                : round($valor * ($peso / $totalPeso), 2);
            $acumulado += $porciones[$idx];
        }

        // ---------- Asientos por ítem ----------
        $movs = 0;
        $totalReingresado = 0.0;
        foreach ($items as $idx => $item) {
            $porcion = $porciones[$idx] ?? 0.0;
            if ($porcion <= 0) continue;

            $producto = $item->variante?->producto;
            $ctaDev = $producto?->cta('devolucion') ?? setting('contable.cta_devolucion_default', '4175');
            $ctaInv = $producto?->cta('inventario') ?? setting('contable.cta_inventario_default', '1435');
            $ctaCosto = $producto?->cta('costo') ?? setting('contable.cta_costo_default', '6135');
            $cc = $producto?->centroCosto();
            $desc = "Dev guía {$pedido->guia} · " . ($producto?->referencia ?? 'sin-ref') . ($cc ? " [CC:{$cc}]" : '');

            MovimientoContable::create([
                'fecha' => $fecha, 'cuenta_puc' => $ctaDev,
                'debe' => $porcion, 'haber' => 0,
                'origen_type' => DropiDevolucion::class, 'origen_id' => $devolucion->id,
                'descripcion' => $desc, 'user_id' => $userId,
            ]);
            $movs++;

            if ($devolucion->destino_inventario === 'reingreso') {
                MovimientoContable::create([
                    'fecha' => $fecha, 'cuenta_puc' => $ctaInv,
                    'debe' => $porcion, 'haber' => 0,
                    'origen_type' => DropiDevolucion::class, 'origen_id' => $devolucion->id,
                    'descripcion' => "Reingreso inv " . ($producto?->referencia ?? '—') . " · {$pedido->guia}",
                    'user_id' => $userId,
                ]);
                MovimientoContable::create([
                    'fecha' => $fecha, 'cuenta_puc' => $ctaCosto,
                    'debe' => 0, 'haber' => $porcion,
                    'origen_type' => DropiDevolucion::class, 'origen_id' => $devolucion->id,
                    'descripcion' => "Reverso costo " . ($producto?->referencia ?? '—') . " · {$pedido->guia}",
                    'user_id' => $userId,
                ]);
                $movs += 2;
                $totalReingresado += $porcion;
            }
        }

        // ---------- CONTRAPARTIDA por el valor total ----------
        // Balancea los débitos a la cuenta de Devoluciones (4175) con el crédito a CxC (1305).
        // Sin esto los asientos serían UNBALANCED y no pasarían validación de partida doble.
        $ctaCxC = setting('contable.cta_cxc_default', '1305');
        MovimientoContable::create([
            'fecha' => $fecha, 'cuenta_puc' => $ctaCxC,
            'debe' => 0, 'haber' => $valor,
            'origen_type' => DropiDevolucion::class, 'origen_id' => $devolucion->id,
            'descripcion' => "Contrapartida devolución guía {$pedido->guia} (CxC)",
            'user_id' => $userId,
        ]);
        $movs++;

        return $movs;
    }
}
