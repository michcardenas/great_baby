<x-filament-panels::page>
    @php
        $config = $this->getConfig();
        $logs = $this->getLogs();
    @endphp

    {{-- Estado de la integración --}}
    <x-filament::section>
        <x-slot name="heading">Estado de la integración</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
            <div style="padding:.75rem;border-radius:.5rem;background:{{ $config->activo ? 'rgba(16,185,129,.1)' : 'rgba(107,114,128,.1)' }};">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Estado</div>
                <div style="font-weight:700;font-size:1.05rem;color:{{ $config->activo ? '#10b981' : '#6b7280' }};">
                    {{ $config->activo ? '✅ Activa' : '⏸️ Inactiva' }}
                </div>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem;">Ambiente: {{ $config->ambiente }}</div>
            </div>
            <div style="padding:.75rem;border-radius:.5rem;background:rgba(59,130,246,.1);">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Última sync catálogos</div>
                <div style="font-weight:700;font-size:.95rem;">{{ $config->sync_catalogos_at?->diffForHumans() ?? 'Nunca' }}</div>
            </div>
            <div style="padding:.75rem;border-radius:.5rem;background:rgba(16,185,129,.1);">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Última sync productos</div>
                <div style="font-weight:700;font-size:.95rem;">{{ $config->sync_productos_at?->diffForHumans() ?? 'Nunca' }}</div>
            </div>
            <div style="padding:.75rem;border-radius:.5rem;background:rgba(245,158,11,.1);">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;">Última sync clientes</div>
                <div style="font-weight:700;font-size:.95rem;">{{ $config->sync_clientes_at?->diffForHumans() ?? 'Nunca' }}</div>
            </div>
        </div>
    </x-filament::section>

    {{-- Configuración --}}
    <div style="margin-top:1rem;">
        <form wire:submit="guardar">
            {{ $this->form }}
            <div style="margin-top:1rem;display:flex;gap:.5rem;">
                @foreach($this->getFormActions() as $a)
                    {{ $a }}
                @endforeach
            </div>
        </form>
    </div>

    {{-- Log de sincronizaciones --}}
    <x-filament::section style="margin-top:1rem;">
        <x-slot name="heading">Historial de sincronizaciones</x-slot>
        <x-slot name="description">Últimas 15 sincronizaciones · nuevos / actualizados / errores / duración</x-slot>
        @if($logs->isEmpty())
            <div style="color:#9ca3af;padding:1rem;text-align:center;">Sin sincronizaciones aún. Configura las credenciales y usa los botones arriba.</div>
        @else
            <table style="width:100%;font-size:.85rem;">
                <thead><tr style="border-bottom:1px solid rgba(156,163,175,.2);">
                    <th style="text-align:left;padding:.5rem;font-size:.7rem;text-transform:uppercase;color:#9ca3af;">Fecha</th>
                    <th style="text-align:left;padding:.5rem;font-size:.7rem;text-transform:uppercase;color:#9ca3af;">Recurso</th>
                    <th style="text-align:left;padding:.5rem;font-size:.7rem;text-transform:uppercase;color:#9ca3af;">Estado</th>
                    <th style="text-align:right;padding:.5rem;font-size:.7rem;text-transform:uppercase;color:#9ca3af;">Nuevos</th>
                    <th style="text-align:right;padding:.5rem;font-size:.7rem;text-transform:uppercase;color:#9ca3af;">Actualizados</th>
                    <th style="text-align:right;padding:.5rem;font-size:.7rem;text-transform:uppercase;color:#9ca3af;">Errores</th>
                    <th style="text-align:right;padding:.5rem;font-size:.7rem;text-transform:uppercase;color:#9ca3af;">Duración</th>
                </tr></thead>
                <tbody>
                @foreach($logs as $l)
                    <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                        <td style="padding:.5rem;color:#9ca3af;font-size:.8rem;">{{ $l->created_at?->format('Y-m-d H:i') }}</td>
                        <td style="padding:.5rem;"><x-filament::badge>{{ $l->recurso }}</x-filament::badge></td>
                        <td style="padding:.5rem;">
                            <x-filament::badge :color="$l->estado === 'exitoso' ? 'success' : ($l->estado === 'parcial' ? 'warning' : 'danger')">
                                {{ ucfirst($l->estado) }}
                            </x-filament::badge>
                        </td>
                        <td style="text-align:right;padding:.5rem;color:#10b981;font-weight:600;">{{ $l->nuevos }}</td>
                        <td style="text-align:right;padding:.5rem;color:#3b82f6;">{{ $l->actualizados }}</td>
                        <td style="text-align:right;padding:.5rem;color:{{ $l->errores > 0 ? '#ef4444' : '#9ca3af' }};">{{ $l->errores }}</td>
                        <td style="text-align:right;padding:.5rem;color:#9ca3af;">{{ $l->duracion_ms }} ms</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
