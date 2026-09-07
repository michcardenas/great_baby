<div style="position:relative;" wire:poll.30s="refrescar" x-data="{ open: @entangle('abierto') }" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open"
            :aria-label="'Notificaciones' + ({{ $noLeidas }} > 0 ? ' ({{ $noLeidas }} sin leer)' : '')"
            aria-haspopup="true" :aria-expanded="open"
            style="position:relative;padding:.5rem;background:transparent;border:0;cursor:pointer;color:currentColor;display:flex;align-items:center;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.35rem;height:1.35rem;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if($noLeidas > 0)
            <span style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;padding:0 4px;background:#ef4444;color:white;border-radius:9999px;font-size:.65rem;font-weight:700;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 2px var(--gray-100,#f3f4f6);">
                {{ $noLeidas > 99 ? '99+' : $noLeidas }}
            </span>
        @endif
    </button>

    <div x-show="open" x-transition
         @click.outside="open = false"
         style="position:absolute;top:100%;right:0;margin-top:.5rem;width:380px;max-width:calc(100vw - 2rem);background:var(--gray-50,#ffffff);border:1px solid rgba(156,163,175,.25);border-radius:.75rem;box-shadow:0 20px 40px rgba(0,0,0,.15);z-index:9999;overflow:hidden;">

        <div style="padding:.75rem 1rem;border-bottom:1px solid rgba(156,163,175,.15);display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,rgba(245,158,11,.08),transparent);">
            <div style="font-weight:700;font-size:.9rem;">🔔 Notificaciones</div>
            @if($noLeidas > 0)
                <button wire:click="marcarTodasLeidas"
                        style="font-size:.75rem;color:#f59e0b;background:transparent;border:0;cursor:pointer;text-decoration:underline;">
                    Marcar todas leídas
                </button>
            @endif
        </div>

        <div style="max-height:400px;overflow-y:auto;">
            @forelse($notificaciones as $n)
                @php
                    $colores = [
                        'success' => ['bg' => 'rgba(16,185,129,.08)', 'br' => '#10b981', 'tx' => '#065f46'],
                        'warning' => ['bg' => 'rgba(245,158,11,.08)', 'br' => '#f59e0b', 'tx' => '#92400e'],
                        'danger'  => ['bg' => 'rgba(239,68,68,.08)',  'br' => '#ef4444', 'tx' => '#991b1b'],
                        'info'    => ['bg' => 'rgba(59,130,246,.08)', 'br' => '#3b82f6', 'tx' => '#1e40af'],
                        'gray'    => ['bg' => 'rgba(107,114,128,.06)', 'br' => '#9ca3af', 'tx' => '#374151'],
                    ];
                    $c = $colores[$n->color] ?? $colores['gray'];
                @endphp
                <a href="{{ $n->url ?: '#' }}"
                   wire:click="marcarLeida({{ $n->id }})"
                   style="display:block;padding:.75rem 1rem;text-decoration:none;color:inherit;border-bottom:1px solid rgba(156,163,175,.08);background:{{ $n->leida_at ? 'transparent' : $c['bg'] }};border-left:3px solid {{ $n->leida_at ? 'transparent' : $c['br'] }};">
                    <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:{{ $n->leida_at ? '500' : '700' }};font-size:.9rem;color:{{ $n->leida_at ? 'inherit' : $c['tx'] }};">
                                {{ $n->titulo }}
                            </div>
                            @if($n->mensaje)
                                <div style="font-size:.8rem;color:#6b7280;margin-top:.2rem;">{{ \Illuminate\Support\Str::limit($n->mensaje, 90) }}</div>
                            @endif
                        </div>
                        <div style="font-size:.7rem;color:#9ca3af;white-space:nowrap;">{{ $n->created_at->diffForHumans() }}</div>
                    </div>
                </a>
            @empty
                <div style="padding:2rem;text-align:center;color:#9ca3af;">
                    <div style="font-size:2rem;">🔕</div>
                    <div style="margin-top:.5rem;font-size:.85rem;">Sin notificaciones nuevas</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
