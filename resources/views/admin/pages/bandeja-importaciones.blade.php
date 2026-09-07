<x-filament-panels::page>
    @php
        $list = $this->importaciones();
        $stats = $this->stats();

        $colorEstado = fn ($e) => match($e) {
            'terminado' => '#10b981',
            'corriendo' => '#3b82f6',
            'fallido'   => '#ef4444',
            default     => '#9ca3af',
        };
        $iconoTipo = fn ($t) => match($t) {
            'facturas'  => '🧾',
            'contactos' => '👤',
            'productos' => '🏷️',
            'dropi'     => '📦',
            'oc'        => '🚢',
            'pagos'     => '💰',
            default     => '📄',
        };
    @endphp

    <div wire:poll.5s>
        <div class="bandeja-kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:1rem;">
            <div style="padding:1rem;background:rgba(107,114,128,.08);border-left:4px solid #9ca3af;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">Total histórico</div>
                <div style="font-size:2rem;font-weight:800;color:#e5e7eb;">{{ $stats['total'] }}</div>
            </div>
            <div style="padding:1rem;background:rgba(59,130,246,.08);border-left:4px solid #3b82f6;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">🔄 Corriendo ahora</div>
                <div style="font-size:2rem;font-weight:800;color:#3b82f6;">{{ $stats['corriendo'] }}</div>
            </div>
            <div style="padding:1rem;background:rgba(16,185,129,.08);border-left:4px solid #10b981;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">✅ Terminadas hoy</div>
                <div style="font-size:2rem;font-weight:800;color:#10b981;">{{ $stats['terminadas_hoy'] }}</div>
            </div>
            <div style="padding:1rem;background:rgba(239,68,68,.08);border-left:4px solid #ef4444;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">❌ Fallidas</div>
                <div style="font-size:2rem;font-weight:800;color:#ef4444;">{{ $stats['fallidas'] }}</div>
            </div>
        </div>

        @if($list->isEmpty())
            <div style="padding:4rem 2rem;text-align:center;background:rgba(15,23,42,.4);border:2px dashed rgba(156,163,175,.25);border-radius:1rem;">
                <div style="font-size:3rem;">📥</div>
                <div style="font-size:1.1rem;color:#9ca3af;margin-top:.5rem;">Aún no hay importaciones en la bandeja</div>
                <div style="font-size:.85rem;color:#6b7280;margin-top:.25rem;">
                    Cada vez que subas un Excel de facturas, contactos, productos, etc., se registra aquí con progreso en vivo.
                </div>
            </div>
        @else
            <div style="background:rgba(15,23,42,.5);border:1px solid rgba(156,163,175,.15);border-radius:1rem;overflow:hidden;">
                <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                    <thead>
                        <tr style="background:rgba(245,158,11,.06);color:#f59e0b;text-transform:uppercase;font-size:.7rem;letter-spacing:1px;">
                            <th style="text-align:left;padding:.75rem 1rem;">Tipo</th>
                            <th style="text-align:left;padding:.75rem 1rem;">Archivo</th>
                            <th style="text-align:left;padding:.75rem 1rem;">Usuario</th>
                            <th style="text-align:left;padding:.75rem 1rem;width:35%;">Progreso</th>
                            <th style="text-align:right;padding:.75rem 1rem;">Filas</th>
                            <th style="text-align:right;padding:.75rem 1rem;">Iniciada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($list as $imp)
                            <tr style="border-top:1px solid rgba(156,163,175,.08);">
                                <td style="padding:.75rem 1rem;font-size:1.2rem;">
                                    {{ $iconoTipo($imp->tipo) }}
                                    <span style="font-size:.75rem;color:#9ca3af;">{{ ucfirst($imp->tipo) }}</span>
                                </td>
                                <td style="padding:.75rem 1rem;color:#e5e7eb;font-family:ui-monospace,monospace;font-size:.85rem;">
                                    {{ \Illuminate\Support\Str::limit($imp->archivo_nombre, 30) }}
                                </td>
                                <td style="padding:.75rem 1rem;color:#9ca3af;">{{ $imp->user?->name ?? '—' }}</td>
                                <td style="padding:.75rem 1rem;">
                                    <div style="display:flex;align-items:center;gap:.5rem;">
                                        <div style="flex:1;height:8px;background:rgba(156,163,175,.15);border-radius:9999px;overflow:hidden;">
                                            <div style="height:100%;width:{{ $imp->porcentaje() }}%;background:linear-gradient(90deg,{{ $colorEstado($imp->estado) }},{{ $colorEstado($imp->estado) }}bb);transition:width .35s;"></div>
                                        </div>
                                        <span style="font-family:ui-monospace,monospace;font-size:.8rem;color:{{ $colorEstado($imp->estado) }};font-weight:700;min-width:38px;text-align:right;">
                                            {{ $imp->porcentaje() }}%
                                        </span>
                                    </div>
                                    <div style="margin-top:.25rem;display:flex;gap:.4rem;font-size:.7rem;color:#9ca3af;">
                                        <span style="color:{{ $colorEstado($imp->estado) }};text-transform:uppercase;font-weight:700;">{{ $imp->estado }}</span>
                                        @if($imp->ok > 0)<span>· ✅ {{ $imp->ok }}</span>@endif
                                        @if($imp->errores > 0)<span style="color:#ef4444;">· ❌ {{ $imp->errores }}</span>@endif
                                    </div>
                                </td>
                                <td style="padding:.75rem 1rem;text-align:right;color:#e5e7eb;font-family:ui-monospace,monospace;">
                                    {{ $imp->procesadas }}/{{ $imp->total_filas }}
                                </td>
                                <td style="padding:.75rem 1rem;text-align:right;color:#9ca3af;font-size:.8rem;">
                                    {{ $imp->iniciada_at?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
