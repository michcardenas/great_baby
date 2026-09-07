<x-filament-panels::page>
    @php
        $p = $this->record;
        $variantes = $this->getVariantes();
        $stockPorUbi = $this->getStockPorUbicacion();
        $imagen = $p->getFirstMediaUrl('imagenes');
        $fmt = fn ($v) => '$' . number_format((float) $v, 0, ',', '.');
    @endphp

    {{-- Header --}}
    <x-filament::section>
        <div style="display:flex;gap:1.5rem;align-items:flex-start;flex-wrap:wrap;">
            @if($imagen)
                <img src="{{ $imagen }}" alt="{{ $p->nombre }}" style="width:120px;height:120px;object-fit:cover;border-radius:.75rem;">
            @else
                <div style="width:120px;height:120px;background:rgba(156,163,175,.15);border-radius:.75rem;display:flex;align-items:center;justify-content:center;color:#6b7280;">
                    <x-filament::icon icon="heroicon-o-photo" style="width:3rem;height:3rem;" />
                </div>
            @endif
            <div style="flex:1;min-width:280px;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;font-family:ui-monospace,monospace;">{{ $p->referencia }}</div>
                <div style="font-size:1.5rem;font-weight:700;margin-top:.25rem;">{{ $p->nombre }}</div>
                <div style="display:flex;gap:.375rem;flex-wrap:wrap;margin-top:.5rem;">
                    @if($p->marca)<x-filament::badge color="info">{{ $p->marca->nombre }}</x-filament::badge>@endif
                    @if($p->categoriaMaestra)<x-filament::badge>{{ $p->categoriaMaestra->nombre }}</x-filament::badge>@endif
                    @if($p->coleccion)<x-filament::badge color="warning">{{ $p->coleccion->nombre }}</x-filament::badge>@endif
                    @if($p->activo)<x-filament::badge color="success">Activo</x-filament::badge>@else<x-filament::badge color="gray">Inactivo</x-filament::badge>@endif
                    @if($p->siigo_id)<x-filament::badge color="info">☁️ SIIGO</x-filament::badge>@endif
                </div>
                @if($p->descripcion)<div style="margin-top:.75rem;color:#9ca3af;font-size:.9rem;">{{ $p->descripcion }}</div>@endif
            </div>
            <div style="text-align:right;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Precio proveedor</div>
                <div style="font-size:1.75rem;font-weight:700;color:#f59e0b;">{{ $fmt($p->precio_proveedor) }}</div>
                <div style="font-size:.8rem;color:#9ca3af;margin-top:.5rem;">{{ $variantes->count() }} variante(s)</div>
            </div>
        </div>
    </x-filament::section>

    {{-- Variantes con códigos --}}
    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Variantes y códigos de barras</x-slot>
        <x-slot name="description">Cada variante tiene su código propio [Ref]-[Color+Diseño]-[Talla] + QR imprimible</x-slot>
        @if($variantes->isEmpty())
            <div style="color:#9ca3af;padding:1rem;text-align:center;">No hay variantes. Agrega al menos una.</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:1rem;">
                @foreach($variantes as $v)
                    @php $stockLocal = $stockPorUbi[$v->codigo_barras] ?? []; $stockTotal = array_sum(array_column($stockLocal, 'saldo')); @endphp
                    <div style="padding:1rem;border:1px solid rgba(156,163,175,.25);border-radius:.75rem;background:rgba(255,255,255,.02);">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;">
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;">
                                    @if($v->color)<span style="display:inline-block;width:.75rem;height:.75rem;border-radius:9999px;background:{{ $v->color->hex ?? '#6b7280' }};border:1px solid rgba(0,0,0,.2);"></span>@endif
                                    {{ $v->color_nombre ?? '—' }}
                                    @if($v->diseno_nombre) · {{ $v->diseno_nombre }}@endif
                                    @if($v->talla) · T{{ $v->talla }}@endif
                                </div>
                                <div style="font-family:ui-monospace,monospace;font-size:.75rem;color:#9ca3af;margin-top:.25rem;word-break:break-all;">{{ $v->codigo_barras }}</div>
                                <div style="margin-top:.5rem;font-size:.85rem;">
                                    <strong>Stock:</strong>
                                    <span style="color:{{ $stockTotal > 0 ? '#10b981' : '#ef4444' }};font-weight:700;">{{ $stockTotal }}</span> uds
                                </div>
                            </div>
                            <img src="{{ route('catalogo.variante.qr', $v) }}" alt="QR" style="width:70px;height:70px;background:#fff;padding:2px;border-radius:.375rem;">
                        </div>
                        <div style="display:flex;gap:.375rem;margin-top:.75rem;flex-wrap:wrap;">
                            <a href="{{ route('catalogo.variante.etiqueta', $v) }}" target="_blank" style="padding:.375rem .625rem;background:rgba(245,158,11,.15);color:#f59e0b;border-radius:.375rem;text-decoration:none;font-size:.75rem;font-weight:600;">🏷️ Etiqueta PDF</a>
                            <a href="{{ route('catalogo.variante.barcode', $v) }}" target="_blank" style="padding:.375rem .625rem;background:rgba(59,130,246,.15);color:#3b82f6;border-radius:.375rem;text-decoration:none;font-size:.75rem;font-weight:600;">📊 Barcode SVG</a>
                            <a href="{{ route('catalogo.variante.qr', $v) }}" target="_blank" style="padding:.375rem .625rem;background:rgba(16,185,129,.15);color:#10b981;border-radius:.375rem;text-decoration:none;font-size:.75rem;font-weight:600;">📱 QR SVG</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
