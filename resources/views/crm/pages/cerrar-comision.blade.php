<x-filament-panels::page>
    <form>{{ $this->form }}</form>

    @php $items = $this->calculadasDelMes(); $fmt = fn($v) => '$' . number_format((float)$v, 0, ',', '.'); @endphp

    @if($items->isEmpty())
        <div style="margin-top:1.5rem;padding:2.5rem;text-align:center;background:rgba(245,158,11,.05);border:2pt dashed rgba(245,158,11,.3);border-radius:1rem;color:#6b7280;">
            <div style="font-size:2.5rem;">🧮</div>
            <div style="margin-top:.5rem;font-size:1.05rem;">Sin cálculos para {{ $mes }}/{{ $anio }}.</div>
            <div style="font-size:.85rem;color:#6b7280;margin-top:.35rem;">Presioná "Calcular comisiones" arriba para generar el corte.</div>
        </div>
    @else
        <div style="margin-top:1.5rem;padding:1rem 1.25rem;background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(59,130,246,.05));border:1pt solid rgba(16,185,129,.25);border-radius:1rem;">
            <div style="font-size:.75rem;color:#10b981;text-transform:uppercase;letter-spacing:2pt;font-weight:700;">💰 Comisiones {{ $mes }}/{{ $anio }} · {{ $items->count() }} vendedor(es)</div>
            <div style="font-size:1.6rem;font-weight:800;color:#10b981;margin-top:.3rem;">Total a pagar: {{ $fmt($items->sum('total_a_pagar')) }}</div>
        </div>

        <div style="margin-top:1rem;background:white;border:1px solid #e5e7eb;border-radius:.75rem;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <thead>
                    <tr style="background:#fef3c7;color:#78350f;text-transform:uppercase;font-size:.7rem;letter-spacing:1pt;">
                        <th style="text-align:left;padding:.6rem 1rem;">Vendedor</th>
                        <th style="text-align:right;padding:.6rem;">Facturado</th>
                        <th style="text-align:right;padding:.6rem;">Cobrado</th>
                        <th style="text-align:right;padding:.6rem;">Base</th>
                        <th style="text-align:right;padding:.6rem;">%</th>
                        <th style="text-align:right;padding:.6rem;">Comisión</th>
                        <th style="text-align:right;padding:.6rem;">Bono</th>
                        <th style="text-align:right;padding:.6rem;">Total</th>
                        <th style="text-align:center;padding:.6rem;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $c)
                        <tr style="border-top:1px solid #e5e7eb;">
                            <td style="padding:.55rem 1rem;font-weight:600;">{{ $c->vendedor?->name }}</td>
                            <td style="padding:.55rem;text-align:right;color:#6b7280;">{{ $fmt($c->total_facturado) }}</td>
                            <td style="padding:.55rem;text-align:right;color:#3b82f6;">{{ $fmt($c->total_cobrado) }}</td>
                            <td style="padding:.55rem;text-align:right;font-weight:600;">{{ $fmt($c->base_comisionable) }}</td>
                            <td style="padding:.55rem;text-align:right;">{{ $c->porcentaje_aplicado }}%</td>
                            <td style="padding:.55rem;text-align:right;">{{ $fmt($c->comision) }}</td>
                            <td style="padding:.55rem;text-align:right;color:#10b981;">{{ $c->bono_meta > 0 ? $fmt($c->bono_meta) : '—' }}</td>
                            <td style="padding:.55rem;text-align:right;font-weight:800;color:#059669;">{{ $fmt($c->total_a_pagar) }}</td>
                            <td style="padding:.55rem;text-align:center;">
                                @php $badge = match($c->estado) {
                                    'aprobado' => 'background:#dbeafe;color:#1e40af',
                                    'pagado' => 'background:#d1fae5;color:#065f46',
                                    default => 'background:#fef3c7;color:#78350f',
                                }; @endphp
                                <span style="padding:.15rem .5rem;border-radius:.75rem;font-size:.7rem;font-weight:700;text-transform:uppercase;{{ $badge }}">
                                    {{ $c->estado }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
