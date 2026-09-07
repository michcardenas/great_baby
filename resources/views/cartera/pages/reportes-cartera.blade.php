<x-filament-panels::page>
    @php
        $edad = $this->getEdadSaldos();
        $morosos = $this->getTopMorosos();
        $consig = $this->getConsignacionesPorAclarar();
        $desc = $this->getDescuentosAplicados();
        $tramos = App\Modules\Cartera\Enums\TramoAntiguedad::cases();
        $fmt = fn ($v) => '$' . number_format($v ?? 0, 0, ',', '.');
    @endphp

    {{-- 1 · Edad de saldos --}}
    <x-filament::section>
        <x-slot name="heading">📊 Edad de saldos por cliente</x-slot>
        <x-slot name="description">Matriz de facturas pendientes por cliente y tramo de antigüedad. Ordenado por saldo total.</x-slot>
        <div style="overflow-x:auto;">
            <table style="width:100%;font-size:.85rem;min-width:800px;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:2px solid rgba(156,163,175,.3);">
                        <th style="text-align:left;padding:.5rem;position:sticky;left:0;background:rgba(15,15,15,.98);">Cliente</th>
                        @foreach($tramos as $t)
                            <th style="text-align:right;padding:.5rem;color:{{ $t->colorHex() }};font-size:.75rem;">{{ $t->label() }}</th>
                        @endforeach
                        <th style="text-align:right;padding:.5rem;color:#f59e0b;font-weight:800;">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($edad as $cliente => $data)
                        <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                            <td style="padding:.5rem;font-weight:600;position:sticky;left:0;background:rgba(15,15,15,.98);">{{ $cliente }}</td>
                            @foreach($tramos as $t)
                                @php $v = $data[$t->value] ?? 0; @endphp
                                <td style="text-align:right;padding:.5rem;color:{{ $v > 0 ? $t->colorHex() : '#4b5563' }};">
                                    {{ $v > 0 ? $fmt($v) : '·' }}
                                </td>
                            @endforeach
                            <td style="text-align:right;padding:.5rem;font-weight:700;color:#f59e0b;">{{ $fmt($data['_total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="99" style="text-align:center;padding:2rem;color:#9ca3af;">Sin saldos vencidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- 2 · Top morosos --}}
    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">🔴 Top 20 morosos</x-slot>
        <x-slot name="description">Clientes con mayor saldo vencido, ordenados descendente.</x-slot>
        @if($morosos->isEmpty())
            <div style="color:#10b981;padding:1rem;">✅ Sin morosos.</div>
        @else
            <table style="width:100%;font-size:.9rem;">
                <thead><tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                    <th style="text-align:left;padding:.5rem;">#</th>
                    <th style="text-align:left;padding:.5rem;">Cliente</th>
                    <th style="text-align:right;padding:.5rem;">Facturas</th>
                    <th style="text-align:right;padding:.5rem;">Mora máx.</th>
                    <th style="text-align:right;padding:.5rem;">Saldo</th>
                </tr></thead>
                <tbody>
                    @foreach($morosos as $i => $m)
                        @php $colMora = $m->mora_max > 90 ? '#ef4444' : ($m->mora_max > 30 ? '#f59e0b' : '#9ca3af'); @endphp
                        <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                            <td style="padding:.5rem;color:#9ca3af;">{{ $i + 1 }}</td>
                            <td style="padding:.5rem;font-weight:600;">{{ $m->contacto?->nombreDisplay() }}</td>
                            <td style="text-align:right;padding:.5rem;">{{ $m->num_facturas }}</td>
                            <td style="text-align:right;padding:.5rem;color:{{ $colMora }};font-weight:600;">{{ $m->mora_max }} d</td>
                            <td style="text-align:right;padding:.5rem;font-weight:700;color:#f59e0b;">{{ $fmt((float) $m->total_saldo) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    {{-- 3 · Consignaciones por aclarar --}}
    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">🏦 Consignaciones por aclarar</x-slot>
        <x-slot name="description">Pagos recibidos sin factura asociada o sin clasificar. Aracely los identifica y aplica manualmente.</x-slot>
        @if($consig->isEmpty())
            <div style="color:#10b981;padding:1rem;">✅ No hay consignaciones pendientes de aclarar.</div>
        @else
            <table style="width:100%;font-size:.9rem;">
                <thead><tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                    <th style="text-align:left;padding:.5rem;">Fecha</th>
                    <th style="text-align:left;padding:.5rem;">Cliente</th>
                    <th style="text-align:left;padding:.5rem;">Referencia</th>
                    <th style="text-align:left;padding:.5rem;">Banco</th>
                    <th style="text-align:right;padding:.5rem;">Monto</th>
                </tr></thead>
                <tbody>
                    @foreach($consig as $p)
                        <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                            <td style="padding:.5rem;color:#9ca3af;">{{ $p->fecha->format('Y-m-d') }}</td>
                            <td style="padding:.5rem;">{{ $p->contacto?->nombreDisplay() ?? '—' }}</td>
                            <td style="padding:.5rem;font-family:ui-monospace,monospace;font-size:.8rem;">{{ $p->referencia ?? '—' }}</td>
                            <td style="padding:.5rem;">{{ $p->banco ?? '—' }}</td>
                            <td style="text-align:right;padding:.5rem;font-weight:700;color:#3b82f6;">{{ $fmt((float) $p->monto_recibido) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    {{-- 4 · Descuentos aplicados este mes --}}
    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">💰 Descuentos aplicados · este mes</x-slot>
        <x-slot name="description">Cuánto está costando la política comercial de descuentos y fletes asumidos.</x-slot>
        @if(empty($desc))
            <div style="color:#9ca3af;padding:1rem;">Sin descuentos aplicados este mes.</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                @foreach($desc as $d)
                    <div style="padding:1rem;border:1px solid rgba(156,163,175,.25);border-radius:.5rem;">
                        <div style="font-size:.75rem;color:#9ca3af;text-transform:uppercase;">{{ $d['label'] }}</div>
                        <div style="font-size:1.5rem;font-weight:700;color:#ef4444;margin-top:.25rem;">{{ $fmt($d['total']) }}</div>
                        <div style="font-size:.85rem;color:#9ca3af;">{{ $d['num'] }} eventos</div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
