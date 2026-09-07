<x-filament-panels::page>
    @php
        $kpis = $this->getKpis();
        $top = $this->getTopProductosCosto();
        $estados = $this->getEstadoImportaciones();
        $fmt = fn ($v) => '$' . number_format((float) $v, 0, ',', '.');
    @endphp

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
        <div style="padding:1rem;background:rgba(59,130,246,.1);border-radius:.75rem;border-left:3px solid #3b82f6;">
            <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">OC 30 días</div>
            <div style="font-size:1.75rem;font-weight:700;">{{ $kpis['oc_periodo'] }}</div>
        </div>
        <div style="padding:1rem;background:rgba(180,83,9,.1);border-radius:.75rem;border-left:3px solid #b45309;">
            <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Comprado 30d</div>
            <div style="font-size:1.5rem;font-weight:700;color:#b45309;">{{ $fmt($kpis['monto_periodo']) }}</div>
        </div>
        <div style="padding:1rem;background:rgba(16,185,129,.1);border-radius:.75rem;border-left:3px solid #10b981;">
            <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Ticket promedio</div>
            <div style="font-size:1.5rem;font-weight:700;">{{ $fmt($kpis['ticket_promedio']) }}</div>
        </div>
        <div style="padding:1rem;background:rgba(245,158,11,.1);border-radius:.75rem;border-left:3px solid #f59e0b;">
            <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Contenedores activos</div>
            <div style="font-size:1.75rem;font-weight:700;">{{ $kpis['contenedores'] }}</div>
        </div>
        <div style="padding:1rem;background:rgba(139,92,246,.1);border-radius:.75rem;border-left:3px solid #8b5cf6;">
            <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Valor en tránsito</div>
            <div style="font-size:1.5rem;font-weight:700;">{{ $fmt($kpis['valor_transito']) }}</div>
        </div>
    </div>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Top 20 productos por costo (90 días)</x-slot>
        <table style="width:100%;font-size:.9rem;">
            <thead>
                <tr style="text-align:left;color:#9ca3af;text-transform:uppercase;font-size:.7rem;border-bottom:1px solid rgba(156,163,175,.3);">
                    <th style="padding:.5rem 0;">Ref</th>
                    <th>Producto</th>
                    <th style="text-align:right;">Cant total</th>
                    <th style="text-align:right;">Precio prom</th>
                    <th style="text-align:right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($top as $p)
                <tr style="border-top:1px solid rgba(156,163,175,.15);">
                    <td style="padding:.5rem 0;font-family:monospace;font-size:.8rem;">{{ $p['referencia'] }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($p['nombre'], 40) }}</td>
                    <td style="text-align:right;">{{ number_format((float)$p['cant_total'], 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ $fmt($p['precio_prom']) }}</td>
                    <td style="text-align:right;font-weight:700;color:#b45309;">{{ $fmt($p['monto_total']) }}</td>
                </tr>
                @endforeach
                @if(empty($top))
                <tr><td colspan="5" style="padding:1rem;text-align:center;color:#9ca3af;">Sin datos en el periodo</td></tr>
                @endif
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Importaciones por estado</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
            @foreach($estados as $e)
            <div style="padding:1rem;background:rgba(255,255,255,.03);border-radius:.5rem;">
                <div style="text-transform:capitalize;color:#9ca3af;font-size:.85rem;">{{ str_replace('_', ' ', $e['estado']) }}</div>
                <div style="font-size:1.5rem;font-weight:700;">{{ $e['cnt'] }} contenedores</div>
                <div style="font-size:.9rem;color:#b45309;">{{ $fmt($e['valor']) }}</div>
            </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
