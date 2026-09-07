<x-filament-panels::page>
    @php
        $sinCobro = $this->getPedidosSinCobro();
        $sanciones = $this->getSanciones();
        $huerfanos = $this->getPagosHuerfanos();
        $totales = $this->getTotales();
        $fmt = fn ($v) => '$' . number_format($v, 0, ',', '.');
    @endphp

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.25rem;">
        <div style="padding:1rem;border:1px solid rgba(245,158,11,.35);border-radius:.75rem;background:rgba(245,158,11,.08);">
            <div style="font-size:.75rem;color:#f59e0b;text-transform:uppercase;">Pendiente de cobro (>3d despacho)</div>
            <div style="font-size:1.75rem;font-weight:700;color:#f59e0b;margin-top:.25rem;">{{ $fmt($totales['esperado']) }}</div>
            <div style="font-size:.85rem;color:#9ca3af;">{{ $sinCobro->count() }} guías</div>
        </div>
        <div style="padding:1rem;border:1px solid rgba(239,68,68,.35);border-radius:.75rem;background:rgba(239,68,68,.08);">
            <div style="font-size:.75rem;color:#ef4444;text-transform:uppercase;">Sanciones detectadas</div>
            <div style="font-size:1.75rem;font-weight:700;color:#ef4444;margin-top:.25rem;">{{ $fmt($totales['sanciones']) }}</div>
            <div style="font-size:.85rem;color:#9ca3af;">{{ $sanciones->count() }} eventos</div>
        </div>
        <div style="padding:1rem;border:1px solid rgba(59,130,246,.35);border-radius:.75rem;background:rgba(59,130,246,.08);">
            <div style="font-size:.75rem;color:#3b82f6;text-transform:uppercase;">Pagos wallet huérfanos</div>
            <div style="font-size:1.75rem;font-weight:700;color:#3b82f6;margin-top:.25rem;">{{ $fmt($totales['huerfanos']) }}</div>
            <div style="font-size:.85rem;color:#9ca3af;">{{ $huerfanos->count() }} sin guía asociada</div>
        </div>
    </div>

    <x-filament::section>
        <x-slot name="heading">1 · Pedidos despachados sin pago (más de 3 días)</x-slot>
        <x-slot name="description">Guías que ya salieron pero la wallet Dropi aún no reporta el pago.</x-slot>
        @if($sinCobro->isEmpty())
            <div style="color:#10b981;">✅ Todo al día · no hay pedidos pendientes de cobro.</div>
        @else
            <table style="width:100%;font-size:.9rem;">
                <thead><tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                    <th style="text-align:left;padding:.5rem;">Guía</th>
                    <th style="text-align:left;padding:.5rem;">Cliente</th>
                    <th style="text-align:left;padding:.5rem;">Despachado</th>
                    <th style="text-align:right;padding:.5rem;">Esperado</th>
                </tr></thead>
                <tbody>
                @foreach($sinCobro as $p)
                    <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                        <td style="padding:.5rem;font-family:ui-monospace,monospace;font-weight:600;">{{ $p->guia }}</td>
                        <td style="padding:.5rem;">{{ $p->cliente_nombre }} · {{ $p->cliente_ciudad }}</td>
                        <td style="padding:.5rem;color:#9ca3af;">{{ $p->despachado_at?->diffForHumans() }}</td>
                        <td style="padding:.5rem;text-align:right;font-weight:600;">{{ $fmt((float) $p->monto_esperado_proveedor) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">2 · Sanciones detectadas por Dropi</x-slot>
        <x-slot name="description">Diferencias entre lo esperado y lo recibido. GB no puede disputar; el sistema solo reporta.</x-slot>
        @if($sanciones->isEmpty())
            <div style="color:#10b981;">✅ Sin sanciones registradas.</div>
        @else
            <table style="width:100%;font-size:.9rem;">
                <thead><tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                    <th style="text-align:left;padding:.5rem;">Guía</th>
                    <th style="text-align:left;padding:.5rem;">Tipo</th>
                    <th style="text-align:right;padding:.5rem;">Esperado</th>
                    <th style="text-align:right;padding:.5rem;">Recibido</th>
                    <th style="text-align:right;padding:.5rem;color:#ef4444;">Diferencia</th>
                </tr></thead>
                <tbody>
                @foreach($sanciones as $s)
                    <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                        <td style="padding:.5rem;font-family:ui-monospace,monospace;font-weight:600;">{{ $s->pedido?->guia ?? '—' }}</td>
                        <td style="padding:.5rem;"><x-filament::badge color="danger">{{ $s->tipo === 'categoria_explicita' ? 'Categoría explícita' : 'Diferencia de precio' }}</x-filament::badge></td>
                        <td style="padding:.5rem;text-align:right;">{{ $fmt((float) $s->monto_esperado) }}</td>
                        <td style="padding:.5rem;text-align:right;">{{ $fmt((float) $s->monto_recibido) }}</td>
                        <td style="padding:.5rem;text-align:right;font-weight:700;color:#ef4444;">{{ $fmt((float) $s->diferencia) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">3 · Pagos wallet sin pedido asociado</x-slot>
        <x-slot name="description">Movimientos de wallet cuya guía no se encontró en el sistema — revisar sincronización.</x-slot>
        @if($huerfanos->isEmpty())
            <div style="color:#10b981;">✅ Todo pago wallet quedó asociado a su guía.</div>
        @else
            <table style="width:100%;font-size:.9rem;">
                <thead><tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                    <th style="text-align:left;padding:.5rem;">Fecha</th>
                    <th style="text-align:left;padding:.5rem;">ID Dropi</th>
                    <th style="text-align:right;padding:.5rem;">Monto</th>
                </tr></thead>
                <tbody>
                @foreach($huerfanos as $h)
                    <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                        <td style="padding:.5rem;color:#9ca3af;">{{ $h->fecha->format('Y-m-d') }}</td>
                        <td style="padding:.5rem;font-family:ui-monospace,monospace;font-size:.8rem;">{{ $h->dropi_movimiento_id ?? '—' }}</td>
                        <td style="padding:.5rem;text-align:right;font-weight:600;color:#3b82f6;">{{ $fmt((float) $h->monto) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
