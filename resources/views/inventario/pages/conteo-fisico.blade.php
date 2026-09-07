<x-filament-panels::page>
    @php
        $t = $this->tomaModel;
        $total = $this->totalItems();
        $contados = $this->itemsContados();
        $pct = $this->porcentaje();
        $faltan = $total - $contados;
    @endphp

    <x-filament::section>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="font-size:.75rem;color:#9ca3af;text-transform:uppercase;">Toma física</div>
                <div style="font-size:1.5rem;font-weight:700;">{{ $t->numero }}</div>
                <div style="color:#9ca3af;">{{ $t->ubicacion?->nombre }} · Fecha: {{ $t->fecha_conteo?->format('Y-m-d') }}</div>
            </div>
            <div style="min-width:240px;">
                <x-filament::badge :color="$t->estado->color()">{{ $t->estado->label() }}</x-filament::badge>
                <div style="font-size:.85rem;color:#9ca3af;margin-top:.35rem;">
                    <strong style="color:#10b981;">{{ $contados }}</strong> contados ·
                    <strong style="color:#f59e0b;">{{ $faltan }}</strong> por contar
                </div>
                <div style="height:8px;background:rgba(156,163,175,.2);border-radius:9999px;margin-top:.5rem;overflow:hidden;">
                    <div style="height:100%;width:{{ $pct }}%;background:linear-gradient(90deg,#10b981,#b45309);transition:width .35s ease;"></div>
                </div>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem;text-align:right;">{{ $pct }}% completado</div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Captura de conteo</x-slot>
        <x-slot name="description">📷 Escanea o escribe la cantidad. Presiona <kbd style="padding:1px 5px;background:rgba(156,163,175,.2);border-radius:3px;">Enter</kbd> para guardar la fila.</x-slot>

        <div style="margin-bottom:1rem;">
            <input type="text" wire:model.live.debounce.300ms="buscar" autofocus
                   placeholder="🔎 Buscar por código o nombre del producto..."
                   style="width:100%;padding:.6rem;border-radius:.5rem;border:1px solid rgba(156,163,175,.35);background:transparent;">
        </div>

        <div style="overflow-x:auto;">
        <table style="width:100%;font-size:.9rem;">
            <thead>
                <tr style="text-align:left;color:#9ca3af;text-transform:uppercase;font-size:.7rem;">
                    <th></th>
                    <th style="padding:.5rem;">Código</th>
                    <th>Producto</th>
                    <th style="text-align:right;">Sistema</th>
                    <th style="text-align:right;">Contado</th>
                    <th style="text-align:right;">Diferencia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->itemsFiltrados() as $item)
                @php
                    $contadoNow = $cantidades[$item->id] ?? null;
                    $dif = $contadoNow !== null ? (int) $contadoNow - $item->saldo_sistema : null;
                    $borderColor = match (true) {
                        $contadoNow === null || $contadoNow === '' => '#6b7280',
                        $dif === 0 => '#10b981',
                        default => '#f59e0b',
                    };
                @endphp
                <tr style="border-top:1px solid rgba(156,163,175,.2);border-left:4px solid {{ $borderColor }};">
                    <td style="padding:.5rem;font-size:1.1rem;">
                        @if($contadoNow !== null && $contadoNow !== '')
                            @if($dif === 0) ✅ @else ⚠️ @endif
                        @else ⏳ @endif
                    </td>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $item->variante?->codigo_barras }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($item->variante?->producto?->nombre ?? '—', 32) }}</td>
                    <td style="text-align:right;color:#9ca3af;">{{ $item->saldo_sistema }}</td>
                    <td style="text-align:right;">
                        <input type="number" inputmode="numeric" pattern="[0-9]*" min="0"
                               wire:model="cantidades.{{ $item->id }}"
                               wire:keydown.enter="guardar({{ $item->id }})"
                               style="width:90px;padding:.4rem;border-radius:.35rem;border:1px solid rgba(156,163,175,.35);background:transparent;text-align:right;font-weight:600;">
                    </td>
                    <td style="text-align:right;font-weight:700;color:{{ $dif > 0 ? '#10b981' : ($dif < 0 ? '#ef4444' : '#9ca3af') }};">
                        @if($dif !== null){{ $dif > 0 ? '+' : '' }}{{ $dif }}@else—@endif
                    </td>
                    <td>
                        <button wire:click="guardar({{ $item->id }})"
                                style="padding:.35rem .625rem;background:rgba(180,83,9,.2);color:#b45309;border:0;border-radius:.35rem;font-weight:600;font-size:.75rem;cursor:pointer;">
                            Guardar
                        </button>
                    </td>
                </tr>
                @endforeach
                @if($this->itemsFiltrados()->isEmpty())
                <tr><td colspan="7" style="padding:1.5rem;text-align:center;color:#9ca3af;">
                    @if($buscar)
                        No encontré ítems con "<strong>{{ $buscar }}</strong>". Prueba con el código de barras completo o con la referencia del producto.
                    @else
                        Esta toma no tiene ítems para contar todavía.
                    @endif
                </td></tr>
                @endif
            </tbody>
        </table>
        </div>

        <div style="margin-top:1rem;padding:.75rem;background:rgba(59,130,246,.1);border-left:3px solid #3b82f6;font-size:.85rem;color:#9ca3af;">
            💡 Cuando termines de contar, vuelve a la lista de tomas y presiona <strong>Cerrar y ajustar</strong>. Ahí es cuando se generan los movimientos del kardex y los asientos contables.
        </div>
    </x-filament::section>
</x-filament-panels::page>
