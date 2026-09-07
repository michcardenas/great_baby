<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="20">
    <title>📺 Bodega · Empaque en vivo</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: radial-gradient(ellipse at top, #1e293b, #020617);
            color: #e5e7eb;
            min-height: 100vh;
            overflow: hidden;
        }
        .contenedor {
            padding: 2.5vw;
            height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 1.5vw;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1vw 1.5vw;
            background: linear-gradient(135deg, rgba(245,158,11,.15), rgba(180,83,9,.05));
            border-left: 6px solid #f59e0b;
            border-radius: 1vw;
        }
        .header .titulo {
            font-size: 3vw;
            font-weight: 900;
            color: #f59e0b;
            letter-spacing: .2vw;
        }
        .header .reloj {
            font-family: ui-monospace, monospace;
            font-size: 3.5vw;
            font-weight: 800;
            color: #f59e0b;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 1.5vw;
            min-height: 0;
        }
        .card {
            background: rgba(15,23,42,.7);
            border: 1px solid rgba(156,163,175,.15);
            border-radius: 1vw;
            padding: 1.5vw;
            display: flex;
            flex-direction: column;
        }
        .card h2 {
            font-size: 1.2vw;
            color: #f59e0b;
            text-transform: uppercase;
            letter-spacing: .25vw;
            margin-bottom: 1vw;
            font-weight: 700;
        }
        .kpis {
            display: grid;
            grid-template-rows: 1fr 1fr;
            gap: 1.5vw;
        }
        .kpi {
            border-radius: 1vw;
            padding: 2vw;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .kpi.verde { background: linear-gradient(135deg, rgba(16,185,129,.2), rgba(16,185,129,.05)); border-left: 8px solid #10b981; }
        .kpi.rojo  { background: linear-gradient(135deg, rgba(239,68,68,.2), rgba(239,68,68,.05));  border-left: 8px solid #ef4444; }
        .kpi .lbl { font-size: 1.4vw; color: #9ca3af; text-transform: uppercase; letter-spacing: .2vw; }
        .kpi .val { font-size: 10vw; font-weight: 900; line-height: 1; margin-top: .5vw; }
        .kpi.verde .val { color: #10b981; }
        .kpi.rojo .val { color: #ef4444; }

        .ranking { flex: 1; overflow: hidden; }
        .ranking table { width: 100%; font-size: 1.6vw; border-collapse: collapse; }
        .ranking th { text-align: left; padding: .8vw; color: #9ca3af; font-size: 1vw; text-transform: uppercase; border-bottom: 1px solid rgba(156,163,175,.2); }
        .ranking td { padding: .8vw; border-bottom: 1px solid rgba(156,163,175,.08); }
        .ranking .pos { font-size: 2.5vw; font-weight: 900; width: 4vw; }
        .ranking .nombre { font-weight: 700; }
        .ranking .total { text-align: right; font-weight: 800; color: #10b981; font-size: 2.2vw; }
        .ranking .prom { text-align: right; color: #9ca3af; font-family: ui-monospace, monospace; }
        .ranking tr:nth-child(1) .nombre { color: #f59e0b; font-size: 1.9vw; }

        .footer {
            padding: 1vw 1.5vw;
            background: rgba(15,23,42,.6);
            border-radius: 1vw;
            display: flex;
            gap: 1.5vw;
            align-items: center;
            overflow-x: auto;
        }
        .footer .lbl {
            font-size: 1vw;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: .2vw;
            font-weight: 700;
            white-space: nowrap;
        }
        .reciente {
            background: rgba(16,185,129,.1);
            border-left: 4px solid #10b981;
            padding: .8vw 1.2vw;
            border-radius: .5vw;
            white-space: nowrap;
        }
        .reciente .guia { font-family: ui-monospace, monospace; color: #10b981; font-weight: 700; font-size: 1.2vw; }
        .reciente .meta { font-size: .95vw; color: #9ca3af; }

        .vacio {
            text-align: center;
            padding: 4vw;
            font-size: 2vw;
            color: #6b7280;
        }

        @keyframes pulseGold {
            0%, 100% { text-shadow: 0 0 20px rgba(245,158,11,.5); }
            50%      { text-shadow: 0 0 40px rgba(245,158,11,.9); }
        }
        .header .reloj { animation: pulseGold 2s ease-in-out infinite; }
    </style>
</head>
<body>
    <div class="contenedor">
        <div class="header">
            <div class="titulo">🏭 BODEGA · EMPAQUE</div>
            <div class="reloj" id="reloj">--:--:--</div>
        </div>

        <div class="grid">
            <div class="kpis">
                <div class="kpi verde">
                    <div class="lbl">✅ Empacados hoy</div>
                    <div class="val">{{ $empacadosHoy }}</div>
                </div>
                <div class="kpi {{ $pendientes > 20 ? 'rojo' : 'verde' }}">
                    <div class="lbl">📦 Pendientes</div>
                    <div class="val">{{ $pendientes }}</div>
                </div>
            </div>

            <div class="card ranking">
                <h2>🏆 Ranking del día</h2>
                @if(empty($ranking))
                    <div class="vacio">📦 Aún no hay empaques hoy</div>
                @else
                    <table>
                        <thead><tr>
                            <th style="width:5vw;">#</th>
                            <th>Operario</th>
                            <th style="text-align:right;">Pedidos</th>
                            <th style="text-align:right;">Prom</th>
                        </tr></thead>
                        <tbody>
                            @foreach($ranking as $i => $r)
                                <tr>
                                    <td class="pos">
                                        @if($i===0)🥇@elseif($i===1)🥈@elseif($i===2)🥉@else{{ $i+1 }}@endif
                                    </td>
                                    <td class="nombre">{{ $r['nombre'] }}</td>
                                    <td class="total">{{ $r['total'] }}</td>
                                    <td class="prom">{{ $r['prom']>0 ? gmdate('i:s', $r['prom']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="footer">
            <div class="lbl">🕒 Últimos:</div>
            @forelse($recientes as $r)
                <div class="reciente">
                    <span class="guia">{{ $r['guia'] }}</span>
                    <span class="meta"> · {{ $r['operario'] }} · {{ $r['ciudad'] }} · {{ gmdate('i:s', $r['seg']) }} · {{ $r['hace'] }}</span>
                </div>
            @empty
                <span style="color:#6b7280;font-size:1.2vw;">— sin empaques todavía hoy</span>
            @endforelse
        </div>
    </div>

    <script>
        const tick = () => document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-CO');
        tick(); setInterval(tick, 1000);
    </script>
</body>
</html>
