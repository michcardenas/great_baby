<x-filament-panels::page>
    @php
        $columnas = $this->getColumnas();
        $filas = $this->getFilas();
    @endphp

    <div style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="{{ url('/admin/reportes-contables') }}" style="padding:.5rem 1rem;background:rgba(156,163,175,.15);border-radius:.5rem;text-decoration:none;color:inherit;font-size:.85rem;">← Volver a reportes</a>
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ $this->titulo() }}</x-slot>
        <x-slot name="description">{{ count($filas) }} registros · datos en tiempo real desde la operación</x-slot>

        @if(empty($filas))
            <div style="color:#9ca3af;padding:2rem;text-align:center;">Sin datos aún para este reporte.</div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:.9rem;border-collapse:collapse;min-width:600px;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(156,163,175,.3);">
                            @foreach($columnas as $c)
                                <th style="text-align:left;padding:.75rem .5rem;font-size:.75rem;text-transform:uppercase;color:#9ca3af;">{{ $c }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($filas as $f)
                        <tr style="border-bottom:1px solid rgba(156,163,175,.1);">
                            @foreach($f as $celda)
                                <td style="padding:.5rem;">{{ $celda }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
