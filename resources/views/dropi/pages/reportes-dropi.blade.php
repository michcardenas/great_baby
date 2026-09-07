<x-filament-panels::page>
    <div style="display:flex;flex-direction:column;gap:2rem;">
        @foreach($this->getBloques() as $key => $bloque)
            <x-filament::section>
                <x-slot name="heading">{{ $bloque['titulo'] }}</x-slot>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
                    @foreach($bloque['reportes'] as $r)
                        <a href="{{ $r['url'] }}" style="display:block;padding:1rem;border:1px solid rgba(156,163,175,.25);border-radius:.75rem;background:rgba(255,255,255,.02);text-decoration:none;color:inherit;transition:transform .1s ease, border-color .1s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='#f59e0b';" onmouseout="this.style.transform='';this.style.borderColor='rgba(156,163,175,.25)';">
                            <div style="display:flex;align-items:flex-start;gap:.75rem;">
                                <div style="width:2.75rem;height:2.75rem;border-radius:.5rem;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;color:#f59e0b;flex-shrink:0;">
                                    <x-filament::icon :icon="$r['icon']" style="width:1.5rem;height:1.5rem;" />
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;">{{ $r['nombre'] }}</div>
                                    <div style="font-size:.85rem;color:#9ca3af;margin-top:.25rem;">{{ $r['desc'] }}</div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
