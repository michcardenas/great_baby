<?php

namespace App\Http\Controllers\App;

use App\Auth\Permisos;
use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\MetodoPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maestro de métodos de pago (efectivo, transferencia, consignación…).
 *
 * Sólo existía en el panel Filament. Al dejar `/admin` para Dropi el ERP se
 * quedaba sin dónde mantenerlo, y de este maestro salen las opciones del
 * formulario de pagos y las cuentas PUC con que se contabiliza cada cobro.
 */
class MetodosPagoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            abort_unless(Permisos::puede($r->user(), 'metodos_pago'), 403);

            return $next($r);
        })];
    }

    public function index(): Response
    {
        return Inertia::render('Cartera/MetodosPago', [
            'metodos' => MetodoPago::orderBy('orden')->orderBy('nombre')->get()
                ->map(fn (MetodoPago $m) => [
                    'id' => $m->id,
                    'codigo' => $m->codigo,
                    'nombre' => $m->nombre,
                    'tipo' => $m->tipo,
                    'requiere_referencia' => (bool) $m->requiere_referencia,
                    'requiere_banco' => (bool) $m->requiere_banco,
                    'requiere_comprobante' => (bool) $m->requiere_comprobante,
                    'cuenta_puc' => $m->cuenta_puc,
                    'siigo_payment_type_id' => $m->siigo_payment_type_id,
                    'siigo_payment_type_nombre' => $m->siigo_payment_type_nombre,
                    'activo' => (bool) $m->activo,
                    'orden' => (int) $m->orden,
                    // Para avisar antes de desactivar uno que está en uso.
                    'usos' => \App\Modules\Cartera\Models\PagoVenta::where('medio_pago', $m->codigo)->count(),
                ]),
            'tipos' => collect(MetodoPago::TIPOS)->map(fn ($l, $v) => ['valor' => $v, 'label' => $l])->values(),
        ]);
    }

    public function guardar(Request $r, ?MetodoPago $metodo = null): RedirectResponse
    {
        $existe = $metodo?->exists ?? false;

        $datos = $r->validate([
            // El código es la llave con que `pagos_venta.medio_pago` referencia
            // el método, así que se restringe a algo estable y sin espacios.
            'codigo' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('metodos_pago', 'codigo')->ignore($metodo?->id)],
            'nombre' => ['required', 'string', 'max:80'],
            'tipo' => ['required', Rule::in(array_keys(MetodoPago::TIPOS))],
            'requiere_referencia' => ['boolean'],
            'requiere_banco' => ['boolean'],
            'requiere_comprobante' => ['boolean'],
            'cuenta_puc' => ['nullable', 'string', 'max:20'],
            // Mapeo a SIIGO: decide a qué tipo de pago entra el recibo.
            'siigo_payment_type_id' => ['nullable', 'integer', 'min:1'],
            'siigo_payment_type_nombre' => ['nullable', 'string', 'max:120'],
            'activo' => ['boolean'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'codigo.regex' => 'El código va en minúsculas, sin espacios ni tildes (ej: transferencia_bancolombia).',
        ]);

        $datos['orden'] = $datos['orden'] ?? 0;

        if ($existe) {
            // Cambiar el código dejaría huérfanos los pagos ya registrados con
            // el código viejo, así que una vez usado no se toca.
            $usos = \App\Modules\Cartera\Models\PagoVenta::where('medio_pago', $metodo->codigo)->count();
            if ($usos > 0 && $datos['codigo'] !== $metodo->codigo) {
                return back()->with('error',
                    "«{$metodo->nombre}» ya tiene {$usos} pago(s) registrado(s): no se le puede cambiar el código. "
                    .'Creá uno nuevo y desactivá este.');
            }

            $metodo->update($datos);
        } else {
            $metodo = MetodoPago::create($datos);
        }

        return back()->with('success', "Método «{$metodo->nombre}» ".($existe ? 'actualizado.' : 'creado.'));
    }

    /**
     * Tipos de pago que ofrece SIIGO para el recibo de caja.
     *
     * Se consulta en vivo —igual que impuestos o tipos de documento— para que
     * el mapeo se haga eligiendo de una lista real y no escribiendo un número
     * a mano. Si SIIGO no responde se devuelve el motivo, no una lista vacía
     * silenciosa.
     */
    public function tiposSiigo(): \Illuminate\Http\JsonResponse
    {
        try {
            $res = app(\App\Modules\Siigo\Clients\SiigoClient::class)
                ->request('GET', '/v1/payment-types?document_type=RC');

            $filas = collect($res->json())
                ->filter(fn ($t) => ($t['active'] ?? true))
                ->map(fn ($t) => ['id' => (int) $t['id'], 'nombre' => (string) ($t['name'] ?? $t['id'])])
                ->sortBy('nombre')->values();

            return response()->json(['ok' => true, 'tipos' => $filas]);
        } catch (\Throwable $t) {
            return response()->json([
                'ok' => false,
                'error' => 'No se pudo consultar SIIGO: '.$t->getMessage(),
                'tipos' => [],
            ]);
        }
    }

    public function eliminar(MetodoPago $metodo): RedirectResponse
    {
        $usos = \App\Modules\Cartera\Models\PagoVenta::where('medio_pago', $metodo->codigo)->count();

        if ($usos > 0) {
            return back()->with('error',
                "«{$metodo->nombre}» tiene {$usos} pago(s) registrado(s). Desactivalo en vez de borrarlo: "
                .'si se borra, esos pagos quedan sin método.');
        }

        $nombre = $metodo->nombre;
        $metodo->delete();

        return back()->with('success', "Método «{$nombre}» eliminado.");
    }
}
