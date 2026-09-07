<x-filament-panels::page>
    @php $o = $this->record; @endphp

    @php $pctRecibido = $o->porcentajeRecibido(); @endphp
    <x-filament::section>
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div style="flex:1;min-width:260px;">
                <div style="font-size:.75rem;color:#9ca3af;text-transform:uppercase;">Orden de compra</div>
                <div style="font-size:1.5rem;font-weight:700;">{{ $o->numero }}</div>
                <div style="margin-top:.5rem;color:#9ca3af;">{{ $o->proveedor?->nombreDisplay() }}</div>
                @if($o->fecha_esperada)
                <div style="margin-top:.35rem;font-size:.85rem;color:{{ $o->fecha_esperada->isPast() && $pctRecibido < 100 ? '#ef4444' : '#9ca3af' }};">
                    📅 Fecha esperada: <strong>{{ $o->fecha_esperada->format('d M Y') }}</strong>
                    @if($o->fecha_esperada->isPast() && $pctRecibido < 100) · <span>Retraso {{ $o->fecha_esperada->diffInDays(now()) }} días</span>@endif
                </div>
                @endif
            </div>
            <div style="text-align:right;min-width:200px;">
                <x-filament::badge :color="$o->estado->color()">{{ $o->estado->label() }}</x-filament::badge>
                <div style="font-size:1.75rem;font-weight:700;color:#b45309;margin-top:.5rem;">
                    ${{ number_format((float) $o->total, 0, ',', '.') }}
                </div>
                <div style="color:#9ca3af;font-size:.85rem;">
                    {{ $o->moneda }} @if($o->moneda !== 'COP') · TRM {{ number_format($o->tasa_cambio, 2) }}@endif
                </div>
            </div>
        </div>

        <div style="margin-top:1rem;">
            <div style="display:flex;justify-content:space-between;font-size:.8rem;color:#9ca3af;margin-bottom:.35rem;">
                <span>Recibido de proveedor</span>
                <span><strong style="color:{{ $pctRecibido >= 100 ? '#10b981' : '#f59e0b' }};">{{ $pctRecibido }}%</strong></span>
            </div>
            <div style="height:8px;background:rgba(156,163,175,.2);border-radius:9999px;overflow:hidden;">
                <div style="height:100%;width:{{ $pctRecibido }}%;background:linear-gradient(90deg,#f59e0b,#10b981);transition:width .35s;"></div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Ítems ({{ $o->items->count() }})</x-slot>
        <table style="width:100%;font-size:.85rem;">
            <thead>
                <tr style="text-align:left;color:#9ca3af;text-transform:uppercase;font-size:.7rem;">
                    <th>Ref</th><th>Descripción</th>
                    <th style="text-align:right;">Cant</th>
                    <th style="text-align:right;">Recibida</th>
                    <th style="text-align:right;">Precio</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($o->items as $it)
                <tr style="border-top:1px solid rgba(156,163,175,.2);">
                    <td style="padding:.5rem 0;">{{ $it->producto?->referencia ?? '—' }}</td>
                    <td>{{ $it->descripcion }}</td>
                    <td style="text-align:right;">{{ rtrim(rtrim(number_format((float)$it->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                    <td style="text-align:right;color:{{ (float)$it->cantidad_recibida >= (float)$it->cantidad ? '#10b981' : '#f59e0b' }};font-weight:600;">
                        {{ rtrim(rtrim(number_format((float)$it->cantidad_recibida, 3, ',', '.'), '0'), ',') }}
                    </td>
                    <td style="text-align:right;">${{ number_format((float)$it->precio_unit, 2, ',', '.') }}</td>
                    <td style="text-align:right;font-weight:700;">${{ number_format((float)$it->total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    @if($o->recepciones->isNotEmpty())
    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Recepciones ({{ $o->recepciones->count() }})</x-slot>
        @foreach($o->recepciones as $r)
        <div style="padding:.75rem;border-left:3px solid #b45309;background:rgba(180,83,9,.05);margin-bottom:.5rem;">
            <strong>{{ $r->numero }}</strong> · {{ $r->fecha_recepcion?->format('Y-m-d') }} ·
            {{ $r->estado }} · <strong>${{ number_format((float)$r->total_recibido, 0, ',', '.') }}</strong>
        </div>
        @endforeach
    </x-filament::section>
    @endif
</x-filament-panels::page>
