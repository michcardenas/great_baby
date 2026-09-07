<x-filament-panels::page>
    @php
        $c = $this->record;
        $credito = $c->es_cliente_b2b ? $this->getCredito() : null;
        $antig = $c->es_cliente_b2b ? $this->getAntiguedad() : null;
        $facturas = $c->facturas()->orderByDesc('fecha_emision')->limit(10)->get();
        $audits = $c->audits()->orderByDesc('created_at')->limit(20)->get();
        $fmt = fn ($v) => '$' . number_format($v, 0, ',', '.');
    @endphp

    {{-- Header --}}
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
            <div style="flex:1;min-width:260px;">
                <div style="font-size:.75rem;text-transform:uppercase;color:#9ca3af;">{{ $c->tipo_documento }} {{ $c->numero_documento }}</div>
                <div style="font-size:1.5rem;font-weight:700;">{{ $c->nombreDisplay() }}</div>
                <div style="font-size:.9rem;color:#9ca3af;margin-top:.25rem;">{{ $c->email ?? '—' }} · {{ $c->telefono ?? '—' }}</div>
                <div style="font-size:.9rem;color:#9ca3af;">{{ $c->direccion }} · {{ $c->ciudad }}, {{ $c->departamento }}</div>
                <div style="display:flex;gap:.375rem;flex-wrap:wrap;margin-top:.75rem;">
                    @if($c->es_cliente_b2b)<x-filament::badge color="info">B2B</x-filament::badge>@endif
                    @if($c->es_cliente)<x-filament::badge color="success">Cliente</x-filament::badge>@endif
                    @if($c->es_proveedor)<x-filament::badge color="warning">Proveedor</x-filament::badge>@endif
                    @if($c->es_vendedor_dropi)<x-filament::badge color="danger">Vendedor Dropi</x-filament::badge>@endif
                    @if($c->es_empleado)<x-filament::badge color="gray">Empleado</x-filament::badge>@endif
                </div>
            </div>
            @if($credito)
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;">
                    <div style="padding:.5rem .75rem;background:rgba(59,130,246,.1);border-radius:.5rem;text-align:center;">
                        <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Cupo</div>
                        <div style="font-weight:700;font-size:1.1rem;">{{ $fmt($credito['cupo']) }}</div>
                    </div>
                    <div style="padding:.5rem .75rem;background:rgba(245,158,11,.1);border-radius:.5rem;text-align:center;">
                        <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Saldo</div>
                        <div style="font-weight:700;font-size:1.1rem;color:#f59e0b;">{{ $fmt($credito['saldo_cartera']) }}</div>
                    </div>
                    <div style="padding:.5rem .75rem;background:rgba(16,185,129,.1);border-radius:.5rem;text-align:center;">
                        <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Disponible</div>
                        <div style="font-weight:700;font-size:1.1rem;color:#10b981;">{{ $fmt($credito['disponible']) }}</div>
                    </div>
                </div>
                @if($credito['tiene_mora_critica'])
                    <div style="width:100%;margin-top:.5rem;padding:.5rem .75rem;background:rgba(239,68,68,.12);border-left:4px solid #ef4444;border-radius:.375rem;color:#ef4444;font-weight:600;">
                        ⚠️ Mora crítica: {{ $credito['dias_mora_max'] }} días — escalar a Gerencia
                    </div>
                @endif
            @endif
        </div>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-top:1rem;">
        {{-- Facturas + antigüedad --}}
        <div>
            @if($antig && $antig['count'] > 0)
                <x-filament::section>
                    <x-slot name="heading">Semáforo de cartera</x-slot>
                    <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:.375rem;">
                        @foreach($antig['por_tramo'] as $key => $data)
                            @php $t = App\Modules\Cartera\Enums\TramoAntiguedad::from($key); @endphp
                            <div style="padding:.5rem;text-align:center;border-radius:.375rem;background:{{ $t->colorHex() }}22;border:1px solid {{ $t->colorHex() }}55;">
                                <div style="font-size:.6rem;color:#9ca3af;text-transform:uppercase;">{{ $t->label() }}</div>
                                <div style="font-weight:700;font-size:.95rem;color:{{ $t->colorHex() }};">{{ $data['count'] }}</div>
                                <div style="font-size:.65rem;color:#9ca3af;">{{ $fmt($data['monto']) }}</div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            <x-filament::section style="margin-top:1rem;">
                <x-slot name="heading">Facturas recientes</x-slot>
                @if($facturas->isEmpty())
                    <div style="color:#9ca3af;padding:1rem;text-align:center;">Sin facturas aún.</div>
                @else
                    <table style="width:100%;font-size:.85rem;">
                        <thead><tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                            <th style="text-align:left;padding:.5rem;">Número</th>
                            <th style="text-align:left;padding:.5rem;">Emisión</th>
                            <th style="text-align:left;padding:.5rem;">Vence</th>
                            <th style="text-align:left;padding:.5rem;">Estado</th>
                            <th style="text-align:right;padding:.5rem;">Total</th>
                            <th style="text-align:right;padding:.5rem;">Saldo</th>
                        </tr></thead>
                        <tbody>
                        @foreach($facturas as $f)
                            <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                                <td style="padding:.5rem;font-family:ui-monospace,monospace;font-weight:600;">{{ $f->numero }}</td>
                                <td style="padding:.5rem;">{{ $f->fecha_emision->format('Y-m-d') }}</td>
                                <td style="padding:.5rem;color:{{ $f->diasMora() > 0 ? '#ef4444' : '#9ca3af' }};">{{ $f->fecha_vencimiento->format('Y-m-d') }}</td>
                                <td style="padding:.5rem;"><x-filament::badge :color="$f->estado->color()">{{ $f->estado->label() }}</x-filament::badge></td>
                                <td style="padding:.5rem;text-align:right;">{{ $fmt((float) $f->total) }}</td>
                                <td style="padding:.5rem;text-align:right;font-weight:700;color:{{ $f->saldo > 0 ? '#f59e0b' : '#10b981' }};">{{ $fmt((float) $f->saldo) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </x-filament::section>
        </div>

        {{-- Timeline historial --}}
        <x-filament::section>
            <x-slot name="heading">Historial</x-slot>
            <x-slot name="description">Cambios auditados en este contacto</x-slot>
            <div style="position:relative;padding-left:1.25rem;">
                <div style="position:absolute;left:.375rem;top:.5rem;bottom:.5rem;width:2px;background:rgba(156,163,175,.25);"></div>
                @forelse($audits as $a)
                    <div style="position:relative;margin-bottom:.9rem;">
                        <div style="position:absolute;left:-1.25rem;top:.375rem;width:.75rem;height:.75rem;border-radius:9999px;background:#f59e0b;border:2px solid rgba(0,0,0,.9);"></div>
                        <div style="font-size:.85rem;font-weight:600;">{{ ucfirst($a->event) }}</div>
                        <div style="font-size:.7rem;color:#9ca3af;">{{ $a->created_at?->format('Y-m-d H:i') }} · {{ $a->user?->name ?? 'Sistema' }}</div>
                        @if($a->old_values || $a->new_values)
                            @php
                                $keys = array_unique(array_merge(array_keys($a->old_values ?? []), array_keys($a->new_values ?? [])));
                            @endphp
                            <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem;">
                                @foreach($keys as $k)
                                    <div><code>{{ $k }}</code>: {{ $a->old_values[$k] ?? '—' }} → {{ $a->new_values[$k] ?? '—' }}</div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div style="color:#9ca3af;font-size:.85rem;">Sin cambios registrados.</div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
