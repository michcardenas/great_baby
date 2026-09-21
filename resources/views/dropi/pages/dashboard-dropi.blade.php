<x-filament-panels::page>
    <div style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;margin-bottom:1.25rem;">
        <div style="flex:1;min-width:280px;">
            {{ $this->form }}
        </div>
    </div>

    @php
        $transp = $this->transportadoras();
        $deptos = $this->departamentos();
        $tiendas = $this->tiendas();
        $productos = $this->topProductos();
        $resultados = $this->resultados();
        $money = fn ($v) => '$' . number_format((float) $v, 0, ',', '.');
        $pctColor = fn ($p) => $p >= 85 ? '#10b981' : ($p >= 70 ? '#f59e0b' : '#ef4444');
        $estadoColor = fn ($e) => match ($e) {
            'entregado' => '#10b981', 'pagado' => '#22c55e',
            'devuelto', 'devolucion_en_camino' => '#f97316',
            'cancelado_dropi', 'cancelado_gb' => '#ef4444',
            default => '#3b82f6',
        };
        $w = fn ($n, $tot) => $tot > 0 ? round($n / $tot * 100, 1) : 0;
    @endphp

    {{-- ===== DESEMPEÑO POR TRANSPORTADORA ===== --}}
    <x-filament::section style="margin-top:1.5rem;">
        <x-slot name="heading">🚚 Desempeño por transportadora</x-slot>
        <x-slot name="description">Dónde y con quién se está ganando o perdiendo la entrega.</x-slot>

        <div style="display:flex;flex-direction:column;gap:1.1rem;">
            @foreach($transp as $t)
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.35rem;">
                        <span style="font-weight:700;">{{ $t->transportadora }}</span>
                        <span style="font-size:.8rem;color:#9ca3af;">{{ number_format($t->ordenes) }} órdenes · {{ $money($t->recaudado) }}</span>
                    </div>
                    <div style="display:flex;height:22px;border-radius:6px;overflow:hidden;background:rgba(148,163,184,.12);">
                        <div style="width:{{ $w($t->entregadas,$t->ordenes) }}%;background:#10b981;" title="Entregadas: {{ $t->entregadas }}"></div>
                        <div style="width:{{ $w($t->devueltas,$t->ordenes) }}%;background:#ef4444;" title="Devueltas: {{ $t->devueltas }}"></div>
                        <div style="width:{{ $w($t->otros,$t->ordenes) }}%;background:#6b7280;" title="En ruta / proceso / canceladas: {{ $t->otros }}"></div>
                    </div>
                    <div style="font-size:.78rem;margin-top:.3rem;color:#9ca3af;">
                        entrega <b style="color:#10b981;">{{ $t->pct_entrega }}%</b> ·
                        devolución <b style="color:#ef4444;">{{ $t->pct_devol }}%</b> ·
                        <span style="color:#10b981;">■</span> {{ $t->entregadas }} entreg.
                        <span style="color:#ef4444;">■</span> {{ $t->devueltas }} devol.
                        <span style="color:#6b7280;">■</span> {{ $t->otros }} en ruta/otros ·
                        ingreso bodega <b>{{ $money($t->bodega) }}</b>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- ===== DESTINOS (DEPTO) + TOP PRODUCTOS ===== --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1.25rem;margin-top:1.25rem;">
        <x-filament::section>
            <x-slot name="heading">📍 Destinos · por departamento</x-slot>
            <x-slot name="description">A qué departamentos se entrega mejor (y dónde se devuelve más).</x-slot>
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:.82rem;border-collapse:collapse;white-space:nowrap;">
                    <thead>
                        <tr style="text-align:left;color:#9ca3af;border-bottom:1px solid rgba(148,163,184,.2);">
                            <th style="padding:.35rem .3rem;">Departamento</th>
                            <th style="padding:.35rem .3rem;text-align:right;">Órd.</th>
                            <th style="padding:.35rem .3rem;text-align:right;">Entr.</th>
                            <th style="padding:.35rem .3rem;text-align:right;">%Ent</th>
                            <th style="padding:.35rem .3rem;text-align:right;">%Dev</th>
                            <th style="padding:.35rem .3rem;text-align:right;">Recaudado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deptos as $d)
                            <tr style="border-bottom:1px solid rgba(148,163,184,.1);">
                                <td style="padding:.4rem .3rem;font-weight:600;">{{ $d->depto }}</td>
                                <td style="padding:.4rem .3rem;text-align:right;">{{ number_format($d->ordenes) }}</td>
                                <td style="padding:.4rem .3rem;text-align:right;">{{ number_format($d->entregadas) }}</td>
                                <td style="padding:.4rem .3rem;text-align:right;color:{{ $pctColor($d->pct_entrega) }};font-weight:600;">{{ $d->pct_entrega }}%</td>
                                <td style="padding:.4rem .3rem;text-align:right;color:#ef4444;">{{ $d->pct_devol }}%</td>
                                <td style="padding:.4rem .3rem;text-align:right;">{{ $money($d->recaudado) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">📦 Top 10 productos · unidades entregadas</x-slot>
            <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left;color:#9ca3af;border-bottom:1px solid rgba(148,163,184,.2);">
                        <th style="padding:.4rem .3rem;">#</th>
                        <th style="padding:.4rem .3rem;">Producto</th>
                        <th style="padding:.4rem .3rem;text-align:right;">Unidades</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productos as $i => $p)
                        <tr style="border-bottom:1px solid rgba(148,163,184,.1);">
                            <td style="padding:.45rem .3rem;color:#6b7280;">{{ $i + 1 }}</td>
                            <td style="padding:.45rem .3rem;font-weight:600;">{{ $p->producto_nombre }}</td>
                            <td style="padding:.45rem .3rem;text-align:right;color:#3b82f6;font-weight:700;">{{ number_format($p->unidades) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    </div>

    {{-- ===== TIENDAS (tabla completa + buscador) ===== --}}
    <x-filament::section style="margin-top:1.25rem;">
        <x-slot name="heading">🏪 Tiendas</x-slot>
        <x-slot name="description">Dónde y con quién se está ganando o perdiendo (ordenado por ingreso a bodega).</x-slot>

        <input type="search" wire:model.live.debounce.400ms="tiendaQ"
            placeholder="Buscar tienda…"
            style="width:100%;max-width:360px;padding:.55rem .85rem;border-radius:.6rem;border:1px solid rgba(148,163,184,.35);background:rgba(148,163,184,.06);color:inherit;margin-bottom:.9rem;">

        <div style="overflow-x:auto;">
            <table style="width:100%;font-size:.82rem;border-collapse:collapse;white-space:nowrap;">
                <thead>
                    <tr style="text-align:left;color:#9ca3af;border-bottom:1px solid rgba(148,163,184,.2);">
                        <th style="padding:.35rem .3rem;">Tienda</th>
                        <th style="padding:.35rem .3rem;text-align:right;">Órdenes</th>
                        <th style="padding:.35rem .3rem;text-align:right;">Entregadas</th>
                        <th style="padding:.35rem .3rem;text-align:right;">%Ent</th>
                        <th style="padding:.35rem .3rem;text-align:right;">Devueltas</th>
                        <th style="padding:.35rem .3rem;text-align:right;">Cancel.</th>
                        <th style="padding:.35rem .3rem;text-align:right;">En ruta</th>
                        <th style="padding:.35rem .3rem;text-align:right;">Recaudado</th>
                        <th style="padding:.35rem .3rem;text-align:right;">Ingreso bodega</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tiendas as $t)
                        <tr style="border-bottom:1px solid rgba(148,163,184,.1);">
                            <td style="padding:.4rem .3rem;font-weight:600;">{{ $t->tienda }}</td>
                            <td style="padding:.4rem .3rem;text-align:right;">{{ number_format($t->ordenes) }}</td>
                            <td style="padding:.4rem .3rem;text-align:right;">{{ number_format($t->entregadas) }}</td>
                            <td style="padding:.4rem .3rem;text-align:right;color:{{ $pctColor($t->pct_entrega) }};font-weight:600;">{{ $t->pct_entrega }}%</td>
                            <td style="padding:.4rem .3rem;text-align:right;color:#f97316;">{{ number_format($t->devueltas) }}</td>
                            <td style="padding:.4rem .3rem;text-align:right;color:#ef4444;">{{ number_format($t->canceladas) }}</td>
                            <td style="padding:.4rem .3rem;text-align:right;color:#9ca3af;">{{ number_format($t->en_ruta) }}</td>
                            <td style="padding:.4rem .3rem;text-align:right;">{{ $money($t->recaudado) }}</td>
                            <td style="padding:.4rem .3rem;text-align:right;color:#10b981;font-weight:700;">{{ $money($t->bodega) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="padding:1rem;text-align:center;color:#9ca3af;">Sin tiendas para "{{ $tiendaQ }}".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ===== BUSCADOR GENERAL + MINI TABLA ===== --}}
    <x-filament::section style="margin-top:1.25rem;">
        <x-slot name="heading">🔎 Buscar pedido</x-slot>
        <x-slot name="description">Guía, producto, tienda o cliente (mínimo 2 letras).</x-slot>

        <input type="search" wire:model.live.debounce.400ms="q"
            placeholder="Ej: 064108971159 · Abejita · Polar baby · Jonathan…"
            style="width:100%;padding:.65rem .9rem;border-radius:.6rem;border:1px solid rgba(148,163,184,.35);background:rgba(148,163,184,.06);color:inherit;margin-bottom:1rem;">

        @if(strlen(trim($q)) >= 2)
            <div style="font-size:.8rem;color:#9ca3af;margin-bottom:.5rem;">{{ count($resultados) }} resultado(s){{ count($resultados) >= 40 ? ' (primeros 40)' : '' }}</div>
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:.84rem;border-collapse:collapse;white-space:nowrap;">
                    <thead>
                        <tr style="text-align:left;color:#9ca3af;border-bottom:1px solid rgba(148,163,184,.2);">
                            <th style="padding:.4rem .3rem;">Guía</th>
                            <th style="padding:.4rem .3rem;">Producto</th>
                            <th style="padding:.4rem .3rem;">Tienda</th>
                            <th style="padding:.4rem .3rem;">Cliente</th>
                            <th style="padding:.4rem .3rem;">Ciudad</th>
                            <th style="padding:.4rem .3rem;">Estado</th>
                            <th style="padding:.4rem .3rem;text-align:right;">Venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($resultados as $r)
                            <tr style="border-bottom:1px solid rgba(148,163,184,.1);">
                                <td style="padding:.45rem .3rem;font-family:ui-monospace,monospace;">{{ $r->guia }}</td>
                                <td style="padding:.45rem .3rem;">{{ \Illuminate\Support\Str::limit($r->producto, 28) }}</td>
                                <td style="padding:.45rem .3rem;">{{ $r->tienda ?: '—' }}</td>
                                <td style="padding:.45rem .3rem;">{{ \Illuminate\Support\Str::limit($r->cliente_nombre, 18) }}</td>
                                <td style="padding:.45rem .3rem;">{{ $r->cliente_ciudad }}</td>
                                <td style="padding:.45rem .3rem;">
                                    <span style="padding:.1rem .5rem;border-radius:1rem;font-size:.72rem;background:{{ $estadoColor($r->estado) }}22;color:{{ $estadoColor($r->estado) }};">{{ ucfirst(str_replace('_',' ',$r->estado)) }}</span>
                                </td>
                                <td style="padding:.45rem .3rem;text-align:right;">{{ $money($r->venta) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="padding:1rem;text-align:center;color:#9ca3af;">Sin coincidencias.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
