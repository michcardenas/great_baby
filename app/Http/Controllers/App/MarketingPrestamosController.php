<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Inventario\Models\Traslado;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LOG-J9 · Panel del rol Marketing con permisos reducidos.
 *
 * Marketing pide productos prestados a bodega para fotos/videos/promoción y
 * los devuelve cuando termina. En vez de un papel en el escritorio, los
 * movimientos quedan como traslados con huella en el kardex.
 *
 * Marketing NO ve/edita precios, inventario comercial ni cartera. Solo
 * mira sus traslados abiertos (lo que le queda por devolver), su almacén y
 * crea nuevas solicitudes de préstamo / devolución (que Aracely o el admin
 * de bodega aprueba desde /app/inventario/traslados).
 */
class MarketingPrestamosController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            $u = $r->user();
            abort_unless($u && ($u->esAracely() || $u->esMarketing()), 403,
                'Pantalla exclusiva del equipo de Marketing.');
            return $next($r);
        })];
    }

    public function index(Request $r): Response
    {
        // Las ubicaciones del equipo marketing: usamos la categoría
        //   ReservaProveedor como contenedor ("préstamo activo · no vende")
        //   y marcamos con prefijo de código 'MKT-' para no mezclarlas con
        //   reservas reales a proveedores.
        $misBodegas = InventarioUbicacion::query()
            ->where('activa', true)
            ->where(function ($q) {
                $q->where('codigo', 'like', 'MKT-%')
                  ->orWhere('categoria', CategoriaUbicacion::ReservaProveedor);
            })
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'ciudad'])
            ->toArray();

        $ids = array_column($misBodegas, 'id');

        // Traslados abiertos (lo que todavía está prestado · estado 'borrador'
        //   o 'ejecutado' sin devolución registrada). Lista cronológica.
        $prestamos = Traslado::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('destino_id', $ids)
                  ->orWhereIn('origen_id', $ids);
            })
            ->with('solicitante:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            // `solicitante` es la RELACIÓN belongsTo(User,'solicitado_por'),
            // no una columna · pedirla en el select tumbaba la pantalla con
            // "Unknown column 'solicitante'".
            ->get(['id', 'numero', 'origen_id', 'destino_id', 'estado',
                   'solicitado_por', 'fecha_ejecucion', 'observaciones', 'created_at'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'numero' => $t->numero,
                'origen_id' => $t->origen_id,
                'destino_id' => $t->destino_id,
                'estado' => $t->estado,
                'solicitante' => $t->solicitante?->name,
                'fecha_ejecucion' => $t->fecha_ejecucion?->format('Y-m-d'),
                'observaciones' => $t->observaciones,
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ])
            ->toArray();

        $abiertos = array_values(array_filter($prestamos, fn ($p) => in_array($p['estado'], ['borrador', 'ejecutado'])));
        $cerrados = array_values(array_filter($prestamos, fn ($p) => ! in_array($p['estado'], ['borrador', 'ejecutado'])));

        // Bodegas comerciales a las que marketing puede pedir prestado.
        $bodegasComerciales = InventarioUbicacion::query()
            ->where('activa', true)
            ->where('categoria', CategoriaUbicacion::Venta)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->toArray();

        return Inertia::render('Marketing/Panel', [
            'mis_bodegas' => $misBodegas,
            'prestamos' => [
                'abiertos' => $abiertos,
                'cerrados' => array_slice($cerrados, 0, 20),
                'total_abiertos' => count($abiertos),
            ],
            'bodegas_comerciales' => $bodegasComerciales,
            'es_super' => (bool) $r->user()?->esAracely(),
        ]);
    }
}
