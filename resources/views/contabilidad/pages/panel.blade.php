<x-filament-panels::page>
    @php
        $stats = $this->getStats();
        $porConciliar = $this->getPorConciliar();
        $fmt = fn ($v) => '$' . number_format((float) $v, 0, ',', '.');
    @endphp

    {{-- 4 stats principales --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div style="padding:1.25rem;border:1px solid rgba(59,130,246,.3);border-radius:.75rem;background:rgba(59,130,246,.06);">
            <div style="font-size:.7rem;color:#3b82f6;text-transform:uppercase;letter-spacing:.05em;">Cartera real por cobrar</div>
            <div style="font-size:1.75rem;font-weight:700;margin-top:.25rem;">{{ $fmt($stats['cartera_total']) }}</div>
        </div>
        <div style="padding:1.25rem;border:1px solid rgba(245,158,11,.3);border-radius:.75rem;background:rgba(245,158,11,.06);">
            <div style="font-size:.7rem;color:#f59e0b;text-transform:uppercase;letter-spacing:.05em;">Movimientos del mes</div>
            <div style="font-size:1.75rem;font-weight:700;margin-top:.25rem;">{{ $stats['por_conciliar'] }}</div>
        </div>
        <div style="padding:1.25rem;border:1px solid rgba(239,68,68,.3);border-radius:.75rem;background:rgba(239,68,68,.06);">
            <div style="font-size:.7rem;color:#ef4444;text-transform:uppercase;letter-spacing:.05em;">Consignaciones por aclarar</div>
            <div style="font-size:1.75rem;font-weight:700;margin-top:.25rem;">{{ $fmt($stats['consignaciones']) }}</div>
        </div>
        <div style="padding:1.25rem;border:1px solid rgba(16,185,129,.3);border-radius:.75rem;background:rgba(16,185,129,.06);">
            <div style="font-size:.7rem;color:#10b981;text-transform:uppercase;letter-spacing:.05em;">Comisiones del periodo</div>
            <div style="font-size:1.75rem;font-weight:700;margin-top:.25rem;">{{ $fmt($stats['comisiones_periodo']) }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        <x-filament::section>
            <x-slot name="heading">Movimientos bancarios recientes</x-slot>
            <x-slot name="description">Últimos ingresos y egresos de caja/banco registrados desde la operación.</x-slot>
            @if(empty($porConciliar))
                <div style="color:#9ca3af;padding:1rem;text-align:center;">Sin movimientos aún.</div>
            @else
                <table style="width:100%;font-size:.9rem;">
                    <thead><tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                        <th style="text-align:left;padding:.5rem;font-size:.75rem;text-transform:uppercase;color:#9ca3af;">Fecha</th>
                        <th style="text-align:left;padding:.5rem;font-size:.75rem;text-transform:uppercase;color:#9ca3af;">Concepto</th>
                        <th style="text-align:right;padding:.5rem;font-size:.75rem;text-transform:uppercase;color:#9ca3af;">Valor</th>
                        <th style="text-align:left;padding:.5rem;font-size:.75rem;text-transform:uppercase;color:#9ca3af;">Clasificación</th>
                    </tr></thead>
                    <tbody>
                    @foreach($porConciliar as $m)
                        <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                            <td style="padding:.5rem;color:#9ca3af;font-family:ui-monospace,monospace;font-size:.85rem;">{{ $m['fecha'] }}</td>
                            <td style="padding:.5rem;font-weight:500;">{{ $m['concepto'] }}</td>
                            <td style="text-align:right;padding:.5rem;font-family:ui-monospace,monospace;font-weight:600;color:{{ $m['tipo'] === 'egreso' ? '#ef4444' : '#10b981' }};">{{ $m['valor'] }}</td>
                            <td style="padding:.5rem;font-size:.85rem;color:#9ca3af;">{{ $m['clasificacion'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Procesos del módulo</x-slot>
            <ul style="list-style:none;padding:0;font-size:.9rem;">
                <li style="padding:.5rem 0;border-bottom:1px solid rgba(156,163,175,.1);">💵 <strong>Caja menor, traslados y arqueo diario</strong> con control de diferencias.</li>
                <li style="padding:.5rem 0;border-bottom:1px solid rgba(156,163,175,.1);">🏦 <strong>Conciliación bancaria</strong> con consignaciones por aclarar.</li>
                <li style="padding:.5rem 0;border-bottom:1px solid rgba(156,163,175,.1);">💳 Pagos con <strong>clasificación de diferencias</strong> (pronto pago, flete GB).</li>
                <li style="padding:.5rem 0;border-bottom:1px solid rgba(156,163,175,.1);">👥 Comisiones sobre <strong>base neta reconocida</strong> por vendedor.</li>
                <li style="padding:.5rem 0;">📦 Garantías a <strong>valor $0</strong>: descuentan inventario, no generan cartera.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
