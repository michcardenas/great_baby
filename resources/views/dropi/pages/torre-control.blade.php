<x-filament-panels::page>
    <div wire:poll.30s="$refresh" x-data="{ hora: '', fecha: '' }"
         x-init="
            const tick = () => {
                const d = new Date();
                hora = d.toLocaleTimeString('es-CO');
                fecha = d.toLocaleDateString('es-CO', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
            };
            tick(); setInterval(tick, 1000);
         ">

        @php
            $k = $this->kpis();
            $s = $this->serie7d();
            $de = $this->distribucionEstados();
            $tc = $this->topCiudades();
            $ro = $this->rankingOperarios();
            $comp = $this->comparativaMes();
            $mapa = $this->mapaCiudades();
            $alertas = $this->alertasCriticas();
            $fmtMoney = fn($v) => '$' . number_format($v, 0, ',', '.');
            $arrow = fn($d) => $d > 0 ? '↑' : ($d < 0 ? '↓' : '→');
            $colorDelta = fn($d, $inverso = false) => (($d > 0 xor $inverso) ? '#10b981' : ($d < 0 ? '#ef4444' : '#9ca3af'));
        @endphp

        {{-- Alertas críticas: banner rojo pulsante + sonido SOLO en transición 0→N --}}
        @if(!empty($alertas))
            <div data-alertas-count="{{ count($alertas) }}"
                 style="margin-bottom:1rem;padding:1rem 1.25rem;background:linear-gradient(90deg,rgba(239,68,68,.15),rgba(239,68,68,.05));border-left:6px solid #ef4444;border-radius:.75rem;animation:pulseAlert 1.5s ease-in-out infinite;">
                @foreach($alertas as $a)
                    <div style="font-size:1rem;font-weight:600;color:#ef4444;margin:.2rem 0;">{{ $a['mensaje'] }}</div>
                @endforeach
            </div>
            <style>@keyframes pulseAlert{0%,100%{box-shadow:0 0 0 rgba(239,68,68,.4)}50%{box-shadow:0 0 24px rgba(239,68,68,.4)}}</style>
            <script>
                (function(){
                    // Sonar cuando aparecen NUEVAS alertas (comparar hash de contenidos, no count).
                    // Antes: solo 0→N. Ahora: (2→3), (1→2 con distinta) también dispara.
                    const prevHash = sessionStorage.getItem('gb.torre.alertasHash') || '';
                    const nowHash = @json(md5(implode('|', array_column($alertas, 'mensaje'))));
                    if (prevHash === nowHash) return;
                    sessionStorage.setItem('gb.torre.alertasHash', nowHash);

                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator(); const g = ctx.createGain();
                        osc.connect(g); g.connect(ctx.destination);
                        osc.type = 'triangle'; osc.frequency.value = 660;
                        g.gain.setValueAtTime(0.15, ctx.currentTime);
                        osc.start(); g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                        osc.stop(ctx.currentTime + 0.4);
                    } catch(e){}
                })();
            </script>
        @else
            <script>sessionStorage.setItem('gb.torre.alertasHash', '');</script>
        @endif

        {{-- Media queries responsive para Torre en tablet/mobile --}}
        <style>
            @media (max-width: 900px) {
                .torre-grid-primary { grid-template-columns: 1fr !important; }
                .torre-grid-secondary { grid-template-columns: 1fr !important; }
            }
        </style>

        {{-- Header con reloj --}}
        <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid rgba(245,158,11,.3);border-radius:1rem;margin-bottom:1rem;">
            <div>
                <div style="font-size:.75rem;color:#f59e0b;text-transform:uppercase;letter-spacing:3px;font-weight:700;">🗼 Torre de Control · Bodega</div>
                <div style="font-size:.85rem;color:#9ca3af;margin-top:.25rem;text-transform:capitalize;" x-text="fecha"></div>
            </div>
            <div style="text-align:right;">
                <div style="font-family:ui-monospace,'Courier New',monospace;font-size:2.5rem;font-weight:700;color:#f59e0b;line-height:1;" x-text="hora"></div>
                <div style="font-size:.7rem;color:#9ca3af;letter-spacing:2px;">SYNC · CADA 30s</div>
            </div>
        </div>

        {{-- KPIs grandes --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:1rem;">

            <div style="padding:1.25rem;background:linear-gradient(135deg,rgba(239,68,68,.15),rgba(239,68,68,.05));border-left:4px solid #ef4444;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">Pendientes por empacar</div>
                <div style="font-size:3rem;font-weight:800;color:#ef4444;line-height:1;" x-data="{n:0}" x-init="let e={{ $k['pendientes'] }},s=Date.now();const anim=()=>{const p=Math.min((Date.now()-s)/500,1);n=Math.round(e*p);if(p<1)requestAnimationFrame(anim)};anim()" x-text="n"></div>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem;">🔥 requieren atención</div>
            </div>

            <div style="padding:1.25rem;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(16,185,129,.05));border-left:4px solid #10b981;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">Empacados hoy</div>
                <div style="font-size:3rem;font-weight:800;color:#10b981;line-height:1;" x-data="{n:0}" x-init="let e={{ $k['empacados_hoy'] }},s=Date.now();const anim=()=>{const p=Math.min((Date.now()-s)/500,1);n=Math.round(e*p);if(p<1)requestAnimationFrame(anim)};anim()" x-text="n"></div>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem;">
                    @if($k['prom_empaque_seg']>0)⏱ prom {{ gmdate('i:s', $k['prom_empaque_seg']) }}@else—@endif
                </div>
            </div>

            <div style="padding:1.25rem;background:linear-gradient(135deg,rgba(59,130,246,.15),rgba(59,130,246,.05));border-left:4px solid #3b82f6;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">Despachados hoy</div>
                <div style="font-size:3rem;font-weight:800;color:#3b82f6;line-height:1;" x-data="{n:0}" x-init="let e={{ $k['despachados_hoy'] }},s=Date.now();const anim=()=>{const p=Math.min((Date.now()-s)/500,1);n=Math.round(e*p);if(p<1)requestAnimationFrame(anim)};anim()" x-text="n"></div>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem;">🚚 salieron a ruta</div>
            </div>

            <div style="padding:1.25rem;background:linear-gradient(135deg,rgba(139,92,246,.15),rgba(139,92,246,.05));border-left:4px solid #8b5cf6;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">Entregados hoy</div>
                <div style="font-size:3rem;font-weight:800;color:#8b5cf6;line-height:1;" x-data="{n:0}" x-init="let e={{ $k['entregados_hoy'] }},s=Date.now();const anim=()=>{const p=Math.min((Date.now()-s)/500,1);n=Math.round(e*p);if(p<1)requestAnimationFrame(anim)};anim()" x-text="n"></div>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem;">✅ confirmados</div>
            </div>

            <div style="padding:1.25rem;background:linear-gradient(135deg,rgba(245,158,11,.15),rgba(245,158,11,.05));border-left:4px solid #f59e0b;border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">Ventas hoy</div>
                <div style="font-size:1.75rem;font-weight:800;color:#f59e0b;line-height:1;font-family:ui-monospace,monospace;">{{ $fmtMoney($k['ventas_hoy']) }}</div>
                <div style="font-size:.75rem;color:{{ $colorDelta($comp['ventas']['delta']) }};margin-top:.5rem;font-weight:600;">
                    {{ $arrow($comp['ventas']['delta']) }} {{ abs($comp['ventas']['delta']) }}% vs mes ant.
                </div>
            </div>

            <div style="padding:1.25rem;background:linear-gradient(135deg,rgba(107,114,128,.15),rgba(107,114,128,.05));border-left:4px solid {{ $k['tasa_devolucion'] > 10 ? '#ef4444' : '#6b7280' }};border-radius:.75rem;">
                <div style="font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;">Devoluciones hoy</div>
                <div style="font-size:3rem;font-weight:800;color:{{ $k['tasa_devolucion'] > 10 ? '#ef4444' : '#e5e7eb' }};line-height:1;">{{ $k['devoluciones_hoy'] }}</div>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem;">{{ $k['tasa_devolucion'] }}% del volumen</div>
            </div>

        </div>

        {{-- Gráficas --}}
        <div class="torre-grid-primary" style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div style="padding:1.25rem;background:rgba(15,23,42,.6);border:1px solid rgba(156,163,175,.15);border-radius:1rem;">
                <div style="font-size:.75rem;color:#f59e0b;text-transform:uppercase;letter-spacing:2px;font-weight:700;margin-bottom:.75rem;">📈 Últimos 7 días · Flujo diario</div>
                <div style="position:relative;height:260px;">
                    <canvas id="chart-linea"></canvas>
                </div>
            </div>

            <div style="padding:1.25rem;background:rgba(15,23,42,.6);border:1px solid rgba(156,163,175,.15);border-radius:1rem;">
                <div style="font-size:.75rem;color:#f59e0b;text-transform:uppercase;letter-spacing:2px;font-weight:700;margin-bottom:.75rem;">🎯 Distribución por estado</div>
                <div style="position:relative;height:260px;">
                    <canvas id="chart-donut"></canvas>
                </div>
            </div>
        </div>

        <div class="torre-grid-secondary" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div style="padding:1.25rem;background:rgba(15,23,42,.6);border:1px solid rgba(156,163,175,.15);border-radius:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                    <div style="font-size:.75rem;color:#f59e0b;text-transform:uppercase;letter-spacing:2px;font-weight:700;">🗺️ Mapa de envíos · Colombia</div>
                    <div style="font-size:.7rem;color:#9ca3af;">{{ count($mapa) }} ciudades</div>
                </div>
                <div id="mapa-colombia" style="height:320px;border-radius:.5rem;overflow:hidden;background:#0f172a;"></div>
            </div>

            <div style="padding:1.25rem;background:rgba(15,23,42,.6);border:1px solid rgba(156,163,175,.15);border-radius:1rem;">
                <div style="font-size:.75rem;color:#f59e0b;text-transform:uppercase;letter-spacing:2px;font-weight:700;margin-bottom:.75rem;">🏆 Ranking operarios hoy</div>
                @if(count($ro) === 0)
                    <div style="text-align:center;color:#6b7280;padding:2.5rem 0;">
                        <div style="font-size:2rem;">📦</div>
                        <div style="font-size:.9rem;margin-top:.5rem;">Aún no hay empaques hoy</div>
                    </div>
                @else
                    <table style="width:100%;font-size:.9rem;">
                        <thead>
                            <tr style="color:#9ca3af;font-size:.7rem;text-transform:uppercase;border-bottom:1px solid rgba(156,163,175,.15);">
                                <th style="text-align:left;padding:.5rem 0;width:30px;">#</th>
                                <th style="text-align:left;">Operario</th>
                                <th style="text-align:right;">Empacados</th>
                                <th style="text-align:right;">Prom</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ro as $i => $r)
                                <tr style="border-bottom:1px solid rgba(156,163,175,.08);">
                                    <td style="padding:.6rem 0;font-weight:700;color:{{ $i===0 ? '#f59e0b' : '#9ca3af' }};">
                                        @if($i===0)🥇@elseif($i===1)🥈@elseif($i===2)🥉@else{{ $i+1 }}@endif
                                    </td>
                                    <td style="color:#e5e7eb;font-weight:500;">{{ $r['nombre'] }}</td>
                                    <td style="text-align:right;font-weight:700;color:#10b981;">{{ $r['total'] }}</td>
                                    <td style="text-align:right;color:#9ca3af;font-family:monospace;">{{ $r['prom_seg']>0 ? gmdate('i:s', $r['prom_seg']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- JSON payloads que se actualizan con cada wire:poll --}}
    <script id="data-serie" type="application/json">@json($s)</script>
    <script id="data-donut" type="application/json">@json($de)</script>
    <script id="data-mapa"  type="application/json">@json($mapa)</script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        (function () {

            const commonPlugins = {
                legend: { labels: { color: '#e5e7eb', font: { size: 11 } } },
                tooltip: { backgroundColor: 'rgba(15,23,42,.95)', titleColor: '#f59e0b', bodyColor: '#e5e7eb' },
            };
            const commonScales = {
                x: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(156,163,175,.08)' } },
                y: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(156,163,175,.08)' }, beginAtZero: true },
            };

            const destroyIfExists = (id) => {
                const c = Chart.getChart(id);
                if (c) c.destroy();
            };

            const readJson = (id) => {
                const el = document.getElementById(id);
                if (!el) return null;
                try { return JSON.parse(el.textContent); } catch (e) { return null; }
            };

            // estilo dark para el tooltip de Leaflet
            const styleTag = document.createElement('style');
            styleTag.textContent = '.leaflet-dark-tooltip{background:#0f172a;color:#f59e0b;border:1px solid #b45309;font-size:.85rem;padding:.4rem .6rem;border-radius:.35rem;box-shadow:0 4px 12px rgba(0,0,0,.5);}';
            document.head.appendChild(styleTag);

            const render = () => {
                const serie = readJson('data-serie') || {labels:[], empacados:[], despachados:[]};
                const donut = readJson('data-donut') || {labels:[], values:[], colors:[]};

                destroyIfExists('chart-linea');
                new Chart(document.getElementById('chart-linea'), {
                    type: 'line',
                    data: {
                        labels: serie.labels,
                        datasets: [
                            { label: 'Empacados', data: serie.empacados, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.15)', tension: .35, fill: true, borderWidth: 2, pointRadius: 4 },
                            { label: 'Despachados', data: serie.despachados, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.15)', tension: .35, fill: true, borderWidth: 2, pointRadius: 4 },
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: commonPlugins, scales: commonScales },
                });

                destroyIfExists('chart-donut');
                new Chart(document.getElementById('chart-donut'), {
                    type: 'doughnut',
                    data: {
                        labels: donut.labels,
                        datasets: [{ data: donut.values, backgroundColor: donut.colors, borderColor: '#0f172a', borderWidth: 2 }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '65%',
                        plugins: {
                            legend: { position: 'right', labels: { color: '#e5e7eb', font: { size: 10 }, padding: 8, boxWidth: 12 } },
                            tooltip: { backgroundColor: 'rgba(15,23,42,.95)', titleColor: '#f59e0b', bodyColor: '#e5e7eb' },
                        }
                    },
                });

                renderMapa();
            };

            let mapaLeaflet = null;
            let mapaCapas = [];
            const renderMapa = () => {
                if (typeof L === 'undefined') return;
                const puntos = readJson('data-mapa') || [];
                const el = document.getElementById('mapa-colombia');
                if (!el) return;

                if (!mapaLeaflet) {
                    mapaLeaflet = L.map('mapa-colombia', {
                        center: [4.6, -74.1], zoom: 5, zoomControl: true,
                        attributionControl: false, preferCanvas: true,
                    });
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                        maxZoom: 18, subdomains: 'abcd',
                    }).addTo(mapaLeaflet);
                }

                mapaCapas.forEach(c => mapaLeaflet.removeLayer(c));
                mapaCapas = [];

                const maxTotal = Math.max(1, ...puntos.map(p => p.total));

                puntos.forEach(p => {
                    const radio = 8 + Math.round((p.total / maxTotal) * 26);
                    const marker = L.circleMarker([p.lat, p.lng], {
                        radius: radio,
                        color: '#f59e0b', weight: 2, fillColor: '#f59e0b',
                        fillOpacity: 0.45,
                    }).bindTooltip(`<strong>${p.ciudad}</strong><br>${p.total} pedidos`,
                        { direction: 'top', className: 'leaflet-dark-tooltip', permanent: false }
                    ).addTo(mapaLeaflet);
                    mapaCapas.push(marker);
                });
            };

            const waitAndRender = () => {
                if (typeof Chart === 'undefined') { setTimeout(waitAndRender, 100); return; }
                if (document.getElementById('chart-linea')) render();
            };
            waitAndRender();

            // re-render tras cada refresh de Livewire (wire:poll)
            document.addEventListener('livewire:navigated', waitAndRender);
            window.addEventListener('load', waitAndRender);
            if (window.Livewire) {
                window.Livewire.hook('morph.updated', () => waitAndRender());
            }
        })();
    </script>
</x-filament-panels::page>
