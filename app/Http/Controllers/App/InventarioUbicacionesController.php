<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Siigo\Jobs\PushUbicacionASiigo;
use App\Modules\Siigo\Models\SiigoCatalogo;
use App\Modules\Siigo\Models\SiigoConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UBIC-2 · CRUD completo de Ubicaciones (bodegas / puntos de venta).
 *
 * Diseño:
 *   - Solo Aracely/Gerencia puede modificar.
 *   - No se puede eliminar una ubicación con stock físico o movimientos
 *     históricos; en su lugar se desactiva (`activa = false`). Esto preserva
 *     la integridad del kardex y los asientos locales ya emitidos.
 *   - Cada ubicación puede mapear una resolución DIAN de SIIGO (UBIC-4/5)
 *     y cuentas PUC propias (UBIC-3 habilita BUG-CTA).
 */
class InventarioUbicacionesController extends Controller
{
    public function index(Request $r): Response
    {
        // LOG · El index es READ-ONLY para AdminBodega (solo mira sus racks).
        //   Crear/editar/eliminar sigue siendo root (los otros métodos del
        //   controller mantienen el esRoot original).
        abort_unless(
            \App\Auth\Permisos::esRoot($r->user()) || $r->user()?->esAdminBodega(),
            403,
            'Solo gerencia administra ubicaciones.'
        );

        // UBIC-8 · Mapa user_id → nombre para pintar el admin de bodega sin N+1.
        $responsables = User::query()
            ->whereIn('id', InventarioUbicacion::whereNotNull('responsable_user_id')->pluck('responsable_user_id'))
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $ubicaciones = InventarioUbicacion::query()
            ->withCount(['movimientos'])
            ->orderBy('activa', 'desc')
            ->orderBy('codigo')
            ->get()
            ->map(function ($u) use ($responsables) {
                $resp = $u->responsable_user_id ? $responsables->get($u->responsable_user_id) : null;
                return [
                    'id' => $u->id,
                    'codigo' => $u->codigo,
                    'nombre' => $u->nombre,
                    'categoria' => $u->categoria?->value,
                    'categoria_label' => $u->categoria?->name,
                    'activa' => (bool) $u->activa,
                    'disponible_para_venta' => (bool) $u->disponible_para_venta,
                    'direccion' => $u->direccion,
                    'ciudad' => $u->ciudad,
                    'responsable_user_id' => $u->responsable_user_id,
                    'responsable_nombre' => $resp?->name,
                    'responsable_email' => $resp?->email,
                    'notas' => $u->notas,
                    'siigo_id' => $u->siigo_id,
                    'siigo_resolution_id' => $u->siigo_resolution_id,
                    'siigo_resolution_name' => $u->siigo_resolution_name,
                    'siigo_resolution_prefix' => $u->siigo_resolution_prefix,
                    'cta_inventario' => $u->cta_inventario,
                    'cta_costo' => $u->cta_costo,
                    'movimientos_count' => (int) $u->movimientos_count,
                ];
            })->all();

        // UBIC-8 · Candidatos a admin de bodega: todo user con rol AdminBodega o
        // Alistador (su admin "senior" natural) + los ya asignados aunque no tengan
        // rol todavía. Al guardar la ubicación auto-promovemos a AdminBodega.
        $candidatos = User::query()
            ->where(function ($q) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['AdminBodega', 'Alistador']))
                    ->orWhereIn('id', InventarioUbicacion::whereNotNull('responsable_user_id')->pluck('responsable_user_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->getRoleNames()->all(),
            ])->all();

        return Inertia::render('Inventario/Ubicaciones/Index', [
            'ubicaciones' => $ubicaciones,
            'categorias' => collect(CategoriaUbicacion::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->name])->all(),
            'resoluciones' => SiigoCatalogo::where('tipo', 'resolutions-fv')->get()
                ->map(fn ($c) => [
                    'id' => (int) $c->codigo,
                    'nombre' => $c->nombre,
                    'prefix' => $c->payload['prefix'] ?? null,
                ])->all(),
            // UBIC-6 · default actual (si la hay) · el front marca con ★ y usa
            // como valor pre-seleccionado al abrir "Nueva ubicación".
            'resolucion_default_id' => (int) setting('siigo.resolucion_fv_default_id', 0) ?: null,
            'warehouses_siigo' => SiigoCatalogo::where('tipo', 'warehouses')->get()
                ->map(fn ($c) => ['id' => (int) $c->codigo, 'nombre' => $c->nombre])->all(),
            // UBIC-8 · usuarios candidatos y endpoint para crear nuevos.
            'candidatos_admin' => $candidatos,
        ]);
    }

    /**
     * UBIC-8 · Crear un nuevo usuario Admin Bodega sin salir del CRUD.
     * Útil cuando Aracely está creando la ubicación y aún no existe el usuario
     * del encargado. Lo deja listo para pre-seleccionarlo como responsable.
     */
    public function crearAdminBodega(Request $r): JsonResponse
    {
        abort_unless(\App\Auth\Permisos::esRoot($r->user()), 403);
        $datos = $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8', 'max:120'],
        ]);
        $pwd = $datos['password'] ?? Str::password(12, symbols: false);
        // FIX-S0 · User::create + syncRoles son dos escrituras separadas; si
        // Spatie falla, el user queda sin rol. Encerrar en transacción.
        $u = DB::transaction(function () use ($datos, $pwd) {
            $user = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => Hash::make($pwd),
            ]);
            $user->syncRoles(['AdminBodega']);
            return $user;
        });
        return response()->json([
            'ok' => true,
            'mensaje' => "Usuario {$u->name} creado como Admin Bodega.",
            'password_generado' => empty($datos['password']) ? $pwd : null,
            'usuario' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'roles' => ['AdminBodega']],
        ]);
    }

    public function guardar(Request $r): JsonResponse
    {
        abort_unless(\App\Auth\Permisos::esRoot($r->user()), 403);

        $datos = $r->validate([
            'id' => ['nullable', 'integer', 'exists:inventario_ubicaciones,id'],
            'codigo' => ['required', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string'],
            'activa' => ['boolean'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'ciudad' => ['nullable', 'string', 'max:80'],
            'responsable_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'siigo_id' => ['nullable', 'integer'],
            'siigo_resolution_id' => ['nullable', 'integer'],
            'siigo_resolution_name' => ['nullable', 'string', 'max:120'],
            'siigo_resolution_prefix' => ['nullable', 'string', 'max:10'],
            'cta_inventario' => ['nullable', 'string', 'max:10'],
            'cta_costo' => ['nullable', 'string', 'max:10'],
        ]);

        // Unicidad del código entre activos.
        $query = InventarioUbicacion::where('codigo', $datos['codigo']);
        if (! empty($datos['id'])) $query->where('id', '!=', $datos['id']);
        if ($query->exists()) {
            return response()->json(['ok' => false, 'mensaje' => "Ya existe otra ubicación con código {$datos['codigo']}."], 422);
        }

        // UBIC-6 · Auto-asignación de resolución SIIGO · si la ubicación es de
        // categoría "venta" (puede facturar) y el usuario no eligió resolución
        // explícitamente, intentamos auto-asignar la default. Esto evita que
        // Aracely tenga que mapearlas una por una.
        $autoAsignada = false;
        $categoriaVenta = \App\Modules\Dropi\Enums\CategoriaUbicacion::from($datos['categoria'])->disponibleParaVenta();
        if (empty($datos['siigo_resolution_id']) && $categoriaVenta) {
            $defaultId = (int) setting('siigo.resolucion_fv_default_id', 0);
            if ($defaultId > 0) {
                $resol = SiigoCatalogo::where('tipo', 'resolutions-fv')->where('codigo', (string) $defaultId)->first();
            } else {
                // Sin default marcada: si solo hay 1 resolución FV, usar esa.
                $resoluciones = SiigoCatalogo::where('tipo', 'resolutions-fv')->get();
                $resol = $resoluciones->count() === 1 ? $resoluciones->first() : null;
            }
            if ($resol) {
                $datos['siigo_resolution_id'] = (int) $resol->codigo;
                $datos['siigo_resolution_name'] = $resol->payload['name'] ?? $resol->nombre;
                $datos['siigo_resolution_prefix'] = $resol->payload['prefix'] ?? null;
                $autoAsignada = true;
            }
        }

        $u = ! empty($datos['id'])
            ? InventarioUbicacion::findOrFail($datos['id'])
            : new InventarioUbicacion();

        $wasNew = empty($datos['id']);
        $siigoDataChanged = ! $wasNew && (
            ($u->getOriginal('codigo') !== ($datos['codigo'] ?? $u->codigo))
            || ($u->getOriginal('nombre') !== ($datos['nombre'] ?? $u->nombre))
        );

        $u->fill($datos);
        $u->save();

        // UBIC-9 · push automático a SIIGO (crea warehouse si es nueva o
        // actualiza si cambió el nombre/código y ya tiene siigo_id).
        $pushEncolado = false;
        if (SiigoConfig::pushAutoActivo() && $u->activa) {
            if ($wasNew || ($siigoDataChanged && $u->siigo_id) || (! $u->siigo_id && $wasNew === false)) {
                PushUbicacionASiigo::dispatch($u->id)->afterCommit();
                $pushEncolado = true;
            }
        }

        // UBIC-8 · si el responsable asignado todavía no tiene rol AdminBodega
        // (ni Aracely/Gerencia, que ya ven todo), lo promovemos automáticamente.
        $promovido = null;
        if (! empty($datos['responsable_user_id'])) {
            $resp = User::find($datos['responsable_user_id']);
            if ($resp && ! $resp->hasAnyRole(['AdminBodega', 'Aracely', 'Gerencia'])) {
                $resp->assignRole('AdminBodega');
                $promovido = $resp->name;
            }
        }

        $msg = $datos['id'] ? "Ubicación {$u->codigo} actualizada." : "Ubicación {$u->codigo} creada.";
        if ($autoAsignada) {
            $msg .= " Resolución SIIGO auto-asignada: [{$u->siigo_resolution_prefix}] {$u->siigo_resolution_name}.";
        }
        if ($promovido) {
            $msg .= " {$promovido} fue promovido a Admin Bodega.";
        }
        if ($pushEncolado) {
            $msg .= $u->siigo_id
                ? " Actualización enviada a SIIGO en segundo plano."
                : " Enviando a SIIGO en segundo plano · el ID aparecerá al terminar.";
        }
        return response()->json([
            'ok' => true,
            'id' => $u->id,
            'auto_asignada' => $autoAsignada,
            'promovido' => $promovido,
            'push_siigo' => $pushEncolado,
            'mensaje' => $msg,
        ]);
    }

    /**
     * UBIC-9 · Botón manual "Enviar a SIIGO" en el CRUD. Útil para:
     *   · ubicaciones viejas creadas antes del push automático,
     *   · reintentar cuando el job de fondo falló.
     */
    public function enviarASiigo(Request $r, int $id): JsonResponse
    {
        abort_unless(\App\Auth\Permisos::esRoot($r->user()), 403);
        $u = InventarioUbicacion::findOrFail($id);
        PushUbicacionASiigo::dispatchManual($u->id);
        return response()->json([
            'ok' => true,
            'mensaje' => $u->siigo_id
                ? "Actualización encolada para la warehouse SIIGO #{$u->siigo_id}."
                : "Creación encolada en SIIGO · el ID aparecerá al terminar.",
        ]);
    }

    /**
     * UBIC-6 · Marca una resolución SIIGO como DEFAULT para auto-asignación.
     * Se guarda en settings y aplica a ubicaciones de categoría venta nuevas.
     */
    public function marcarResolucionDefault(Request $r): JsonResponse
    {
        abort_unless(\App\Auth\Permisos::esRoot($r->user()), 403);
        $datos = $r->validate(['siigo_resolution_id' => ['nullable', 'integer']]);
        $id = (int) ($datos['siigo_resolution_id'] ?? 0);
        \App\Support\Reglas::set('siigo.resolucion_fv_default_id', $id > 0 ? $id : null);
        return response()->json([
            'ok' => true,
            'mensaje' => $id > 0
                ? "Resolución #{$id} marcada como default para auto-asignar a nuevas ubicaciones de venta."
                : "Default limpiado · nuevas ubicaciones no tendrán resolución auto-asignada.",
        ]);
    }

    public function eliminar(Request $r, int $id): JsonResponse
    {
        abort_unless(\App\Auth\Permisos::esRoot($r->user()), 403);

        $u = InventarioUbicacion::findOrFail($id);
        $mov = $u->movimientos()->exists();
        // En vez de romper el kardex, desactivamos y damos feedback claro.
        if ($mov) {
            $u->activa = false;
            $u->save();
            return response()->json([
                'ok' => true,
                'desactivada' => true,
                'mensaje' => "La ubicación {$u->codigo} tiene movimientos históricos · se marcó como INACTIVA en lugar de borrarse.",
            ]);
        }
        $codigo = $u->codigo;
        $u->delete();
        return response()->json(['ok' => true, 'mensaje' => "Ubicación {$codigo} eliminada."]);
    }

    public function toggleActiva(Request $r, int $id): JsonResponse
    {
        abort_unless(\App\Auth\Permisos::esRoot($r->user()), 403);
        $u = InventarioUbicacion::findOrFail($id);
        $u->activa = ! $u->activa;
        $u->save();
        return response()->json(['ok' => true, 'activa' => (bool) $u->activa]);
    }

    /**
     * UBIC-4 · Trae de SIIGO las resoluciones DIAN activas de facturas venta.
     * Primero sincroniza document-types (para refrescar la base) y luego
     * extrae las FV al catálogo propio. Devuelve el nuevo listado para que
     * el front refresque el dropdown sin recargar.
     */
    public function sincronizarResoluciones(Request $r): JsonResponse
    {
        abort_unless(\App\Auth\Permisos::esRoot($r->user()), 403);
        try {
            $svc = app(\App\Modules\Siigo\Services\SiigoService::class);
            $resumen = $svc->sincronizarCatalogos();
            $resoluciones = \App\Modules\Siigo\Models\SiigoCatalogo::where('tipo', 'resolutions-fv')
                ->get()
                ->map(fn ($c) => [
                    'id' => (int) $c->codigo,
                    'nombre' => $c->nombre,
                    'prefix' => $c->payload['prefix'] ?? null,
                ])->values()->all();
            return response()->json([
                'ok' => true,
                'mensaje' => "Trajimos {$resumen['resolutions-fv']} resoluciones de factura venta desde SIIGO.",
                'resoluciones' => $resoluciones,
                'resumen' => $resumen,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Error consultando SIIGO: '.$e->getMessage(),
            ], 500);
        }
    }
}
