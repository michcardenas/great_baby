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
        <x-slot name="heading">Fecha de corte</x-slot>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div style="display:flex;flex-direction:column;gap:.25rem;">
                <label style="{{ $labelCss }}">A la fecha</label>
                <input type="date" wire:model.live="corte" style="{{ $inputCss }}">
            </div>
            <div wire:loading style="align-self:center;font-size:.8rem;color:#9ca3af;">Calculando…</div>
        </div>
    </x-filament::section>

    @php
        $bloque = function (string $titulo, array $filas, float $total, string $color) use ($money) {
            $html = '<tr style="border-bottom:1px solid rgba(156,163,175,.2);"><td style="padding:.6rem .5rem;font-weight:700;color:'.$color.';text-transform:uppercase;font-size:.8rem;">'.$titulo.'</td><td></td></tr>';
            foreach ($filas as $r) {
                $html .= '<tr><td style="padding:.35rem .5rem .35rem 1.5rem;color:#9ca3af;">'.$r['cuenta'].'</td><td style="padding:.35rem .5rem;text-align:right;font-variant-numeric:tabular-nums;">'.$money($r['valor']).'</td></tr>';
            }
            return $html;
        };
    @endphp

    <x-filament::section>
        <x-slot name="heading">Balance General</x-slot>
        <x-slot name="description">A {{ $d['corte'] }} · datos en tiempo real desde los asientos</x-slot>

        @if($d['cuadra'])
            <div style="margin-bottom:.75rem;padding:.5rem .8rem;background:rgba(16,185,129,.12);border-left:3px solid #10b981;border-radius:.4rem;color:#10b981;font-size:.85rem;font-weight:600;">✓ Cuadrado — Activo = Pasivo + Patrimonio</div>
        @else
            <div style="margin-bottom:.75rem;padding:.5rem .8rem;background:rgba(239,68,68,.12);border-left:3px solid #ef4444;border-radius:.4rem;color:#ef4444;font-size:.85rem;font-weight:600;">⚠ Descuadre de {{ $money($d['descuadre']) }} — revisar asientos</div>
        @endif

        <div style="overflow-x:auto;">
            <table style="width:100%;font-size:.92rem;border-collapse:collapse;min-width:520px;">
                <tbody>
                    {{-- ACTIVO --}}
                    {!! $bloque('Activo', $d['activo'], $d['totActivo'], '#3b82f6') !!}
                    <tr style="background:rgba(59,130,246,.1);">
                        <td style="padding:.6rem .5rem;font-weight:800;">TOTAL ACTIVO</td>
                        <td style="padding:.6rem .5rem;text-align:right;font-weight:800;color:#3b82f6;font-variant-numeric:tabular-nums;">{{ $money($d['totActivo']) }}</td>
                    </tr>

                    <tr><td colspan="2" style="height:.75rem;"></td></tr>

                    {{-- PASIVO --}}
                    {!! $bloque('Pasivo', $d['pasivo'], $d['totPasivo'], '#f59e0b') !!}
                    <tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                        <td style="padding:.4rem .5rem;font-weight:600;">Total pasivo</td>
                        <td style="padding:.4rem .5rem;text-align:right;font-weight:700;color:#f59e0b;font-variant-numeric:tabular-nums;">{{ $money($d['totPasivo']) }}</td>
                    </tr>

                    {{-- PATRIMONIO --}}
                    {!! $bloque('Patrimonio', $d['patrimonio'], $d['totPatrimonio'], '#a78bfa') !!}
                    <tr>
                        <td style="padding:.35rem .5rem .35rem 1.5rem;color:#9ca3af;">Resultado del ejercicio</td>
                        <td style="padding:.35rem .5rem;text-align:right;font-variant-numeric:tabular-nums;color:{{ $d['resultadoEjercicio'] >= 0 ? '#10b981' : '#ef4444' }};">{{ $money($d['resultadoEjercicio']) }}</td>
                    </tr>
                    <tr style="background:rgba(167,139,250,.12);">
                        <td style="padding:.6rem .5rem;font-weight:800;">TOTAL PASIVO + PATRIMONIO</td>
                        <td style="padding:.6rem .5rem;text-align:right;font-weight:800;color:#a78bfa;font-variant-numeric:tabular-nums;">{{ $money($d['totalPasivoPatrimonio']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
