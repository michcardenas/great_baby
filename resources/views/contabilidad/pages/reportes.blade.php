<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;">
        @foreach($this->getReportes() as $slug => $r)
            @if($r['listo'])
                <a href="{{ url('/admin/contabilidad/reporte/' . $slug) }}" style="display:block;padding:1.25rem;border:1px solid rgba(156,163,175,.25);border-radius:.75rem;background:rgba(255,255,255,.02);text-decoration:none;color:inherit;transition:transform .1s ease, border-color .1s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='#f59e0b';" onmouseout="this.style.transform='';this.style.borderColor='rgba(156,163,175,.25)';">
                    <div style="display:flex;align-items:flex-start;gap:.75rem;">
                        <div style="width:2.75rem;height:2.75rem;border-radius:.5rem;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;color:#f59e0b;flex-shrink:0;">
                            <x-filament::icon :icon="$r['icono']" style="width:1.5rem;height:1.5rem;" />
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:1rem;">{{ $r['titulo'] }}</div>
                            <div style="font-size:.85rem;color:#9ca3af;margin-top:.25rem;">{{ $r['descripcion'] }}</div>
                        </div>
                    </div>
                </a>
            @else
                <div style="padding:1.25rem;border:1px dashed rgba(156,163,175,.25);border-radius:.75rem;opacity:.55;">
                    <div style="display:flex;align-items:flex-start;gap:.75rem;">
                        <div style="width:2.75rem;height:2.75rem;border-radius:.5rem;background:rgba(107,114,128,.12);display:flex;align-items:center;justify-content:center;color:#6b7280;flex-shrink:0;">
                            <x-filament::icon :icon="$r['icono']" style="width:1.5rem;height:1.5rem;" />
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:1rem;">{{ $r['titulo'] }}</div>
                            <div style="font-size:.85rem;color:#9ca3af;margin-top:.25rem;">{{ $r['descripcion'] }}</div>
                            <div style="margin-top:.5rem;"><x-filament::badge color="gray">Disponible cuando llegue M1 CRM / M9 RRHH</x-filament::badge></div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</x-filament-panels::page>
