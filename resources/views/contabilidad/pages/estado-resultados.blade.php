<x-filament-panels::page>
    @php
        $d = $this->datos();
        $money = fn ($v) => '$' . number_format((float) $v, 0, ',', '.');
        $inputCss = 'padding:.45rem .6rem;border:1px solid rgba(156,163,175,.35);border-radius:.5rem;background:transparent;color:inherit;font-size:.85rem;';
        $labelCss = 'font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;';
    @endphp

    <div style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="{{ url('/admin/reportes-contables') }}" style="padding:.5rem 1rem;background:rgba(156,163,175,.15);border-radius:.5rem;text-decoration:none;color:inherit;font-size:.85rem;">← Reportes contables</a>
    </div>

    <x-filament::section style="margin-bottom:1rem;">
        <x-slot name="heading">Periodo</x-slot>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div style="display:flex;flex-direction:column;gap:.25rem;">
                <label style="{{ $labelCss }}">Desde</label>
                <input type="date" wire:model.live="desde" style="{{ $inputCss }}">
            </div>
            <div style="display:flex;flex-direction:column;gap:.25rem;">
                <label style="{{ $labelCss }}">Hasta</label>
                <input type="date" wire:model.live="hasta" style="{{ $inputCss }}">
            </div>
            <div wire:loading style="align-self:center;font-size:.8rem;color:#9ca3af;">Calculando…</div>
        </div>
    </x-filament::section>

    @php
        $sección = function (string $titulo, array $filas, float $total, string $color) use ($money) {
            return compact('titulo', 'filas', 'total', 'color');
        };
    @endphp

    <x-filament::section>
        <x-slot name="heading">Estado de Resultados</x-slot>
        <x-slot name="description">Del {{ $d['desde'] }} al {{ $d['hasta'] }} · datos en tiempo real desde los asientos</x-slot>

        <div style="overflow-x:auto;">
            <table style="width:100%;font-size:.92rem;border-collapse:collapse;min-width:520px;">
                <tbody>
                    {{-- INGRESOS --}}
                    <tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                        <td style="padding:.6rem .5rem;font-weight:700;color:#10b981;text-transform:uppercase;font-size:.8rem;">Ingresos operacionales</td>
                        <td></td>
                    </tr>
                    @foreach($d['ingresos'] as $r)
                        <tr>
                            <td style="padding:.35rem .5rem .35rem 1.5rem;color:#9ca3af;">{{ $r['cuenta'] }}</td>
                            <td style="padding:.35rem .5rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $money($r['valor']) }}</td>
                        </tr>
                    @endforeach
                    <tr style="border-bottom:2px solid rgba(156,163,175,.25);">
                        <td style="padding:.5rem;font-weight:600;">Total ingresos</td>
                        <td style="padding:.5rem;text-align:right;font-weight:700;color:#10b981;font-variant-numeric:tabular-nums;">{{ $money($d['totIngresos']) }}</td>
                    </tr>

                    {{-- COSTO DE VENTAS --}}
                    <tr>
                        <td style="padding:.6rem .5rem;font-weight:700;color:#f59e0b;text-transform:uppercase;font-size:.8rem;">(-) Costo de ventas</td>
                        <td></td>
                    </tr>
                    @foreach($d['costos'] as $r)
                        <tr>
                            <td style="padding:.35rem .5rem .35rem 1.5rem;color:#9ca3af;">{{ $r['cuenta'] }}</td>
                            <td style="padding:.35rem .5rem;text-align:right;font-variant-numeric:tabular-nums;">({{ $money($r['valor']) }})</td>
                        </tr>
                    @endforeach
                    <tr style="border-bottom:2px solid rgba(156,163,175,.25);">
                        <td style="padding:.5rem;font-weight:600;">Total costo de ventas</td>
                        <td style="padding:.5rem;text-align:right;font-weight:700;color:#f59e0b;font-variant-numeric:tabular-nums;">({{ $money($d['totCostos']) }})</td>
                    </tr>

                    {{-- UTILIDAD BRUTA --}}
                    <tr style="background:rgba(16,185,129,.08);">
                        <td style="padding:.6rem .5rem;font-weight:800;">= Utilidad bruta <span style="color:#9ca3af;font-weight:500;font-size:.8rem;">({{ $d['margenBruto'] }}%)</span></td>
                        <td style="padding:.6rem .5rem;text-align:right;font-weight:800;font-variant-numeric:tabular-nums;">{{ $money($d['utilidadBruta']) }}</td>
                    </tr>

                    {{-- GASTOS --}}
                    <tr>
                        <td style="padding:.6rem .5rem;font-weight:700;color:#ef4444;text-transform:uppercase;font-size:.8rem;">(-) Gastos operacionales</td>
                        <td></td>
                    </tr>
                    @foreach($d['gastos'] as $r)
                        <tr>
                            <td style="padding:.35rem .5rem .35rem 1.5rem;color:#9ca3af;">{{ $r['cuenta'] }}</td>
                            <td style="padding:.35rem .5rem;text-align:right;font-variant-numeric:tabular-nums;">({{ $money($r['valor']) }})</td>
                        </tr>
                    @endforeach
                    <tr style="border-bottom:2px solid rgba(156,163,175,.25);">
                        <td style="padding:.5rem;font-weight:600;">Total gastos operacionales</td>
                        <td style="padding:.5rem;text-align:right;font-weight:700;color:#ef4444;font-variant-numeric:tabular-nums;">({{ $money($d['totGastos']) }})</td>
                    </tr>

                    {{-- UTILIDAD OPERACIONAL --}}
                    <tr style="background:{{ $d['utilidadOperacional'] >= 0 ? 'rgba(16,185,129,.14)' : 'rgba(239,68,68,.14)' }};">
                        <td style="padding:.75rem .5rem;font-weight:800;font-size:1.02rem;">= Utilidad operacional <span style="color:#9ca3af;font-weight:500;font-size:.8rem;">({{ $d['margenOperacional'] }}%)</span></td>
                        <td style="padding:.75rem .5rem;text-align:right;font-weight:800;font-size:1.02rem;color:{{ $d['utilidadOperacional'] >= 0 ? '#10b981' : '#ef4444' }};font-variant-numeric:tabular-nums;">{{ $money($d['utilidadOperacional']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if(empty($d['ingresos']) && empty($d['costos']) && empty($d['gastos']))
            <div style="color:#9ca3af;padding:1.5rem;text-align:center;">Sin movimientos en el periodo seleccionado.</div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
