<x-filament-panels::page>
    <form wire:submit="registrar">
        {{-- Paso 1: escanear guía --}}
        {{ $this->form->getComponents()[0] }}

        @if($pedido)
            {{-- Paso 2: contexto ANTES de decidir destino (se decide viendo la info) --}}
            <x-filament::section style="margin-top:1rem;">
                <x-slot name="heading">2 · Contexto del pedido</x-slot>
                <x-slot name="description">Traído automáticamente por número de guía</x-slot>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                    <div><strong>Cliente:</strong> {{ $pedido->cliente_nombre }}</div>
                    <div><strong>Ciudad:</strong> {{ $pedido->cliente_ciudad }}</div>
                    <div><strong>Transportadora:</strong> {{ $pedido->transportadora }}</div>
                    <div><strong>Estado:</strong> <x-filament::badge>{{ $pedido->estado->label() }}</x-filament::badge></div>
                    <div style="grid-column:1/-1;">
                        <strong>Productos:</strong>
                        <ul style="margin-top:.5rem;list-style:none;padding:0;">
                            @foreach($pedido->items as $it)
                                <li style="padding:.75rem;background:rgba(156,163,175,.1);border-radius:.5rem;margin-bottom:.375rem;">
                                    <span style="display:inline-block;min-width:2.75rem;height:2.75rem;line-height:2.75rem;text-align:center;border-radius:9999px;background:#f59e0b;color:#fff;font-weight:700;margin-right:.75rem;">{{ $it->cantidad }}</span>
                                    <span style="font-family:ui-monospace,monospace;font-size:.9rem;">{{ $it->sku_dropi }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @if($pedido->ari_factura_id)
                        <div style="grid-column:1/-1;padding:.75rem;background:rgba(59,130,246,.1);border-radius:.5rem;font-size:.875rem;">
                            <strong>Factura electrónica:</strong> {{ $pedido->ari_factura_id }} — al confirmar se genera la nota crédito.
                        </div>
                    @endif
                </div>
            </x-filament::section>

            {{-- Paso 3: decidir destino DESPUÉS de ver contexto --}}
            <div style="margin-top:1rem;">
                {{ $this->form->getComponents()[1] }}
            </div>
        @endif

        <div style="margin-top:1.25rem;display:flex;gap:.5rem;">
            @foreach($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
