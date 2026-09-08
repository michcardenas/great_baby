<?php

namespace App\Http\Controllers\Dropi;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\EmpaqueRegistro;
use Illuminate\Support\Facades\DB;

class ModoTvController extends Controller
{
    /**
     * Vista fullscreen para pantalla en pared de bodega.
     * Requiere sesión Aracely/Alistador (aunque sea "solo lectura", contiene ranking + guías).
     */
    public function empaque()
    {
        $u = auth()->user();
        abort_unless($u && ($u->esAracely() || $u->hasAnyRole(['Alistador'])), 403);

        $hoy = today();

        $empacadosHoy = EmpaqueRegistro::whereDate('fin_at', $hoy)->where('estado', 'completado')->count();
        $pendientes = DropiPedido::whereIn('estado', [EstadoPedidoDropi::Pending, EstadoPedidoDropi::Alistando])->count();

        $ranking = DB::table('empaques_registro')
            ->join('users', 'users.id', '=', 'empaques_registro.operario_id')
            ->whereDate('fin_at', $hoy)
            ->where('estado', 'completado')
            ->selectRaw('users.name, COUNT(*) as total, AVG(duracion_segundos) as prom')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($r) => [
                'nombre' => $r->name,
                'total' => (int) $r->total,
                'prom' => (int) ($r->prom ?? 0),
            ])->toArray();

        // Últimos 3 pedidos empacados (celebrar)
        $recientes = EmpaqueRegistro::with(['pedido', 'operario'])
            ->whereDate('fin_at', $hoy)
            ->where('estado', 'completado')
            ->orderByDesc('fin_at')
            ->limit(3)
            ->get()
            ->map(fn ($r) => [
                'guia' => (string) $r->pedido?->guia,
                'operario' => (string) $r->operario?->name,
                'ciudad' => (string) $r->pedido?->cliente_ciudad,
                'seg' => (int) $r->duracion_segundos,
                'hace' => $r->fin_at?->diffForHumans(),
            ])
            ->toArray();

        return view('dropi.tv.empaque', compact('empacadosHoy', 'pendientes', 'ranking', 'recientes'));
    }
}
