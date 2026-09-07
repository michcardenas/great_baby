<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <div>
                <div style="font-size:.75rem;color:#9ca3af;text-transform:uppercase;">Bodega</div>
                <select wire:model.live="ubicacionId" style="padding:.5rem .75rem;border-radius:.5rem;border:1px solid rgba(156,163,175,.35);background:transparent;">
                    @foreach($this->bodegas() as $b)
                        <option value="{{ $b->id }}">{{ $b->nombre }} ({{ $b->codigo }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Stock por variante</x-slot>
        <x-slot name="description">Snapshot en tiempo real. Se calcula sumando el kardex.</x-slot>

        @php $saldos = $this->saldos(); @endphp
        <div style="max-height:600px;overflow-y:auto;">
        <table style="width:100%;font-size:.85rem;">
            <thead style="position:sticky;top:0;background:rgba(0,0,0,.85);">
                <tr style="text-align:left;color:#9ca3af;text-transform:uppercase;font-size:.7rem;">
                    <th style="padding:.5rem;">Ref</th>
                    <th>Producto</th>
                    <th>Variante</th>
                    <th>Código</th>
                    <th style="text-align:right;">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($saldos as $s)
                <tr style="border-top:1px solid rgba(156,163,175,.15);">
                    <td style="padding:.5rem;font-family:monospace;font-size:.75rem;">{{ $s->referencia ?? '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($s->producto_nombre ?? '—', 32) }}</td>
                    <td>{{ $s->color_nombre ?? '—' }}@if($s->talla) · T{{ $s->talla }}@endif</td>
                    <td style="font-family:monospace;font-size:.75rem;color:#9ca3af;">{{ $s->codigo_barras }}</td>
                    <td style="text-align:right;font-weight:700;color:{{ $s->saldo > 0 ? '#10b981' : '#ef4444' }};">
                        {{ number_format((float)$s->saldo, 0, ',', '.') }}
                    </td>
                </tr>
                @endforeach
                @if(empty($saldos))
                <tr><td colspan="5" style="padding:1rem;text-align:center;color:#9ca3af;">Sin stock en esta bodega.</td></tr>
                @endif
            </tbody>
        </table>
        </div>

        <div style="margin-top:1rem;font-size:.85rem;color:#9ca3af;">
            Total variantes con stock: <strong style="color:inherit;">{{ count($saldos) }}</strong>
            · Unidades totales: <strong style="color:#b45309;">{{ number_format(collect($saldos)->sum('saldo'), 0, ',', '.') }}</strong>
        </div>
    </x-filament::section>
</x-filament-panels::page>
