<div x-data="{
        open: @entangle('abierto').live,
        activeIdx: 0,
        get resultados() { return Array.from(document.querySelectorAll('a[data-cmdk-result]')); },
        moveDown() { const n = this.resultados.length; if (!n) return; this.activeIdx = (this.activeIdx + 1) % n; this.highlight(); },
        moveUp() { const n = this.resultados.length; if (!n) return; this.activeIdx = (this.activeIdx - 1 + n) % n; this.highlight(); },
        activate() {
            const r = this.resultados[this.activeIdx];
            if (r) { r.click(); }
        },
        highlight() {
            this.resultados.forEach((el, i) => {
                el.style.background = i === this.activeIdx ? 'rgba(245,158,11,.15)' : 'transparent';
                if (i === this.activeIdx) el.scrollIntoView({block:'nearest'});
            });
        },
    }"
     x-init="
        window.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                $wire.abrir();
                setTimeout(() => document.getElementById('cmdk-input')?.focus(), 100);
            }
            if (e.key === 'Escape' && open) { $wire.cerrar(); }
            if (open) {
                if (e.key === 'ArrowDown') { e.preventDefault(); moveDown(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); moveUp(); }
                else if (e.key === 'Enter' && document.getElementById('cmdk-input')?.matches(':focus')) { e.preventDefault(); activate(); }
            }
        });
        // Cuando abre, resetear índice
        $watch('open', v => { if (v) { activeIdx = 0; setTimeout(() => highlight(), 200); } });
     "
     @keydown.escape.window="$wire.cerrar()">

    <div x-show="open" x-transition.opacity
         @click.self="$wire.cerrar()"
         role="dialog"
         aria-modal="true"
         aria-label="Buscador global"
         style="position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:9997;display:flex;align-items:flex-start;justify-content:center;padding-top:12vh;">

        <div style="width:100%;max-width:640px;background:#0f172a;border:1px solid rgba(245,158,11,.35);border-radius:.85rem;overflow:hidden;box-shadow:0 25px 50px rgba(0,0,0,.6);">
            <div style="padding:.75rem 1rem;border-bottom:1px solid rgba(156,163,175,.15);display:flex;align-items:center;gap:.75rem;">
                <span style="font-size:1.2rem;" aria-hidden="true">🔍</span>
                <input type="text"
                       id="cmdk-input"
                       wire:model.live.debounce.150ms="q"
                       @keyup.debounce.300ms="setTimeout(() => highlight(), 100)"
                       placeholder="Busca facturas, clientes, pedidos, productos… (esc para cerrar, ↑↓ navegar, Enter abrir)"
                       aria-label="Buscar en el ERP"
                       style="flex:1;background:transparent;border:0;outline:none;color:#f59e0b;font-size:1rem;font-weight:500;font-family:ui-monospace,monospace;">
                <kbd style="padding:.15rem .4rem;background:rgba(156,163,175,.15);border-radius:.25rem;font-size:.7rem;color:#9ca3af;font-family:ui-monospace,monospace;">esc</kbd>
            </div>

            <div style="max-height:60vh;overflow-y:auto;">
                @if(empty($resultados) && mb_strlen(trim($q)) >= 2)
                    <div style="padding:3rem 2rem;text-align:center;color:#9ca3af;">
                        <div style="font-size:2rem;" aria-hidden="true">🤷</div>
                        <div style="margin-top:.5rem;">Nada encontrado para "<strong>{{ $q }}</strong>"</div>
                        <div style="margin-top:.5rem;font-size:.75rem;color:#6b7280;">
                            Es posible que tu rol no tenga permiso para ver ese tipo de dato.
                        </div>
                    </div>
                @elseif(empty($resultados))
                    <div style="padding:2rem;text-align:center;color:#6b7280;">
                        <div style="font-size:.85rem;">Escribe al menos 2 letras para buscar.</div>
                        <div style="margin-top:1rem;font-size:.75rem;">Encuentra: 🧾 Facturas · 👤 Contactos · 📦 Pedidos Dropi · 🏷️ Productos</div>
                    </div>
                @else
                    @foreach($resultados as $r)
                        <a href="{{ $r['url'] }}"
                           data-cmdk-result
                           @click="$wire.cerrar()"
                           style="display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;text-decoration:none;color:#e5e7eb;border-bottom:1px solid rgba(156,163,175,.06);transition:background .1s;"
                           onmouseover="this.style.background='rgba(245,158,11,.15)'"
                           onmouseout="this.style.background=''">
                            <span style="font-size:1.4rem;flex-shrink:0;" aria-hidden="true">{{ $r['icono'] }}</span>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;color:#f59e0b;font-size:.9rem;">{{ \Illuminate\Support\Str::limit($r['titulo'], 60) }}</div>
                                <div style="font-size:.75rem;color:#9ca3af;">{{ \Illuminate\Support\Str::limit($r['sub'], 70) }}</div>
                            </div>
                            <span style="font-size:.65rem;padding:.15rem .45rem;background:rgba(156,163,175,.15);border-radius:.25rem;color:#9ca3af;white-space:nowrap;">{{ $r['tipo'] }}</span>
                        </a>
                    @endforeach
                @endif
            </div>

            <div style="padding:.5rem .75rem;background:rgba(15,23,42,.6);border-top:1px solid rgba(156,163,175,.1);font-size:.7rem;color:#9ca3af;display:flex;justify-content:space-between;">
                <span>
                    <kbd id="cmdk-shortcut" style="padding:.1rem .35rem;background:rgba(156,163,175,.15);border-radius:.2rem;font-family:ui-monospace,monospace;">⌘ K</kbd> abrir ·
                    <kbd style="padding:.1rem .35rem;background:rgba(156,163,175,.15);border-radius:.2rem;font-family:ui-monospace,monospace;">↑↓</kbd> navegar ·
                    <kbd style="padding:.1rem .35rem;background:rgba(156,163,175,.15);border-radius:.2rem;font-family:ui-monospace,monospace;">Enter</kbd> abrir ·
                    <kbd style="padding:.1rem .35rem;background:rgba(156,163,175,.15);border-radius:.2rem;font-family:ui-monospace,monospace;">esc</kbd> cerrar
                </span>
                <span>{{ count($resultados) }} resultados</span>
            </div>
            <script>
                (function () {
                    const el = document.getElementById('cmdk-shortcut');
                    if (!el) return;
                    const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
                    el.textContent = isMac ? '⌘ K' : 'Ctrl K';
                })();
            </script>
        </div>
    </div>
</div>
