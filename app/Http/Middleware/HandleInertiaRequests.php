<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user('web');
        $cliente = $request->user('cliente');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'roles' => $user->getRoleNames()->all(),
                    'es_aracely' => method_exists($user, 'esAracely') && $user->esAracely(),
                ] : null,
                'cliente' => $cliente ? [
                    'id' => $cliente->id,
                    'email' => $cliente->email,
                    'nombre_display' => $cliente->razon_social ?: $cliente->nombre_completo,
                    'lista_precios_id' => $cliente->lista_precios_id,
                ] : null,
            ],
            // C-QA-D-8: badge de pedidos B2B nuevos para Aracely (lazy prop, sólo se evalúa si el layout la lee).
            'badges' => [
                'pedidos_b2b_pendientes' => fn () => $user?->esAracely()
                    ? (int) \App\Modules\Portal\Models\PedidoCliente::where('estado', 'enviado')->count()
                    : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'empaqueResultado' => fn () => $request->session()->get('empaqueResultado'),
                'errores' => fn () => $request->session()->get('errores'),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
        ];
    }
}
