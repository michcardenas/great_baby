<x-filament-panels::page>
    <x-filament::section>
        <div style="font-size:.8rem;color:#9ca3af;margin-bottom:.5rem;">📷 Enfoca la lectora aquí — al escanear, la búsqueda se dispara sola.</div>
        <div style="display:flex;gap:.5rem;">
            <input type="text" wire:model="buscarCodigo" wire:keydown.enter="buscar" autofocus
                   placeholder="Escanea o escribe el código de la variante..."
                   style="flex:1;padding:.6rem;border-radius:.5rem;border:1px solid rgba(156,163,175,.35);background:transparent;font-family:monospace;">
            <button wire:click="buscar"
                    style="padding:.6rem 1rem;background:#b45309;color:#fff;border:0;border-radius:.5rem;font-weight:600;cursor:pointer;">
                Buscar
            </button>
        </div>
    </x-filament::section>

    @if($v = $this->variante())
    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">{{ $v->producto?->nombre ?? 'Variante' }}</x-slot>
        <x-slot name="description">
            Ref {{ $v->producto?->referencia }} · {{ $v->color_nombre }}@if($v->talla) · T{{ $v->talla }}@endif ·
            <code>{{ $v->codigo_barras }}</code>
        </x-slot>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-bottom:1rem;">
            @foreach($this->saldos() as $s)
            <div style="padding:.75rem;background:rgba(180,83,9,.1);border-radius:.5rem;border-left:3px solid #b45309;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">{{ $s->ubicacion }}</div>
                <div style="font-size:1.25rem;font-weight:700;">{{ number_format((float)$s->saldo, 0, ',', '.') }}</div>
            </div>
            @endforeach
        </div>

        <div style="max-height:500px;overflow-y:auto;">
        <table style="width:100%;font-size:.85rem;">
            <thead style="position:sticky;top:0;background:rgba(0,0,0,.85);">
                <tr style="text-align:left;color:#9ca3af;text-transform:uppercase;font-size:.7rem;">
                    <th style="padding:.5rem;">Fecha</th>
                    <th>Tipo</th>
                    <th>Bodega</th>
                    <th style="text-align:right;">Cant</th>
                    <th>Referencia</th>
                    <th>Usuario</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->movimientos() as $m)
                <tr style="border-top:1px solid rgba(156,163,175,.15);">
                    <td style="padding:.4rem .5rem;color:#9ca3af;">{{ $m->created_at?->format('Y-m-d H:i') }}</td>
                    <td><x-filament::badge :color="str_contains($m->tipo, 'salida') || (int)$m->cantidad < 0 ? 'danger' : 'success'">{{ $m->tipo }}</x-filament::badge></td>
                    <td>{{ $m->ubicacion?->nombre }}</td>
                    <td style="text-align:right;font-weight:700;color:{{ (int)$m->cantidad > 0 ? '#10b981' : '#ef4444' }};">
                        {{ (int)$m->cantidad > 0 ? '+' : '' }}{{ (int)$m->cantidad }}
                    </td>
                    <td style="font-size:.75rem;color:#9ca3af;">{{ $m->referencia_tipo ? class_basename($m->referencia_tipo) . ' #' . $m->referencia_id : '—' }}</td>
                    <td style="font-size:.75rem;">{{ $m->user?->name ?? '—' }}</td>
                    <td style="font-size:.75rem;color:#9ca3af;">{{ \Illuminate\Support\Str::limit($m->notas ?? '', 30) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </x-filament::section>
    @elseif($buscarCodigo)
    <x-filament::section style="margin-top:1rem;">
        <div style="text-align:center;color:#ef4444;padding:2rem;">
            Variante no encontrada con código <code>{{ $buscarCodigo }}</code>
        </div>
    </x-filament::section>
    @endif
</x-filament-panels::page>
