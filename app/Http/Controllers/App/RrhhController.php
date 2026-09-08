<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Rrhh\Models\Candidato;
use App\Modules\Rrhh\Models\Empleado;
use App\Modules\Rrhh\Models\Vacante;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M9 · Gestión Humana MVP:
 *   Vacantes → Candidatos con pipeline → Empleados con inducción.
 *   (Nómina NO está en base según contrato — es ampliación.)
 */
class RrhhController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            $u = $r->user();
            abort_unless($u && ($u->esAracely() || $u->hasAnyRole(['Gerente', 'RRHH'])), 403);
            return $next($r);
        })];
    }

    // ---------- VACANTES ----------
    public function vacantesIndex(): Response
    {
        $vacantes = Vacante::withCount('candidatos')->orderByDesc('id')->paginate(30);
        return Inertia::render('Rrhh/Vacantes/Index', [
            'vacantes' => $vacantes->through(fn ($v) => [
                'id' => $v->id, 'titulo' => $v->titulo, 'area' => $v->area,
                'modalidad' => $v->modalidad, 'tipo_contrato' => $v->tipo_contrato,
                'estado' => $v->estado, 'candidatos_count' => (int) $v->candidatos_count,
                'apertura' => $v->fecha_apertura?->toDateString(),
                'salario_min' => (float) $v->salario_min, 'salario_max' => (float) $v->salario_max,
            ]),
        ]);
    }

    public function vacanteCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'area' => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'requisitos' => ['nullable', 'string', 'max:2000'],
            'salario_min' => ['nullable', 'numeric'],
            'salario_max' => ['nullable', 'numeric'],
            'modalidad' => ['required', 'in:presencial,remoto,hibrido'],
            'tipo_contrato' => ['required', 'in:indefinido,obra_labor,prestacion_servicios,aprendizaje,temporal'],
        ]);
        Vacante::create([...$data, 'estado' => 'abierta', 'fecha_apertura' => now(), 'creado_por' => auth()->id()]);
        return back()->with('success', 'Vacante creada.');
    }

    public function vacanteShow(int $vacante): Response
    {
        $v = Vacante::with('candidatos')->findOrFail($vacante);
        return Inertia::render('Rrhh/Vacantes/Show', [
            'vacante' => [
                'id' => $v->id, 'titulo' => $v->titulo, 'area' => $v->area,
                'descripcion' => $v->descripcion, 'requisitos' => $v->requisitos,
                'modalidad' => $v->modalidad, 'tipo_contrato' => $v->tipo_contrato,
                'estado' => $v->estado,
                'salario_min' => (float) $v->salario_min, 'salario_max' => (float) $v->salario_max,
            ],
            'candidatos' => $v->candidatos->map(fn ($c) => [
                'id' => $c->id, 'nombre' => $c->nombre, 'email' => $c->email, 'telefono' => $c->telefono,
                'etapa' => $c->etapa, 'calificacion' => $c->calificacion,
                'notas' => $c->notas, 'fecha' => $c->created_at?->format('Y-m-d'),
            ])->all(),
        ]);
    }

    public function candidatoCrear(Request $r, int $vacante): RedirectResponse
    {
        $data = $r->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);
        Candidato::create([...$data, 'vacante_id' => $vacante, 'etapa' => 'nuevo']);
        return back()->with('success', 'Candidato agregado.');
    }

    public function candidatoActualizar(Request $r, int $candidato): RedirectResponse
    {
        $data = $r->validate([
            'etapa' => ['required', 'in:nuevo,revision,entrevista,prueba,oferta,contratado,descartado'],
            'calificacion' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);
        Candidato::findOrFail($candidato)->update($data);
        return back()->with('success', 'Candidato actualizado.');
    }

    // ---------- EMPLEADOS ----------
    public function empleadosIndex(Request $request): Response
    {
        $estado = (string) $request->input('estado', 'activo');
        $emps = Empleado::when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('nombre')->paginate(50);

        return Inertia::render('Rrhh/Empleados/Index', [
            'empleados' => $emps->through(fn ($e) => [
                'id' => $e->id, 'nombre' => $e->nombre,
                'documento' => $e->tipo_documento . ' ' . $e->numero_documento,
                'email' => $e->email, 'telefono' => $e->telefono,
                'cargo' => $e->cargo, 'area' => $e->area,
                'tipo_contrato' => $e->tipo_contrato,
                'ingreso' => $e->fecha_ingreso?->toDateString(),
                'estado' => $e->estado, 'induccion' => $e->estado_induccion,
                'salario' => (float) $e->salario,
            ]),
            'filtro' => $estado,
        ]);
    }

    public function empleadoCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'tipo_documento' => ['required', 'string', 'max:5'],
            'numero_documento' => ['required', 'string', 'max:20', 'unique:rrhh_empleados,numero_documento'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'cargo' => ['required', 'string', 'max:100'],
            'area' => ['required', 'string', 'max:50'],
            'tipo_contrato' => ['required', 'in:indefinido,obra_labor,prestacion_servicios,aprendizaje,temporal'],
            'salario' => ['nullable', 'numeric', 'min:0'],
            'fecha_ingreso' => ['required', 'date'],
            'candidato_id' => ['nullable', 'integer', 'exists:rrhh_candidatos,id'],
        ]);
        Empleado::create([...$data, 'estado' => 'activo', 'estado_induccion' => 'pendiente']);
        // Si viene de candidato, marcarlo contratado
        if (! empty($data['candidato_id'])) {
            Candidato::where('id', $data['candidato_id'])->update(['etapa' => 'contratado']);
        }
        return back()->with('success', 'Empleado registrado.');
    }

    public function empleadoInduccion(Request $r, int $empleado): RedirectResponse
    {
        $data = $r->validate(['estado_induccion' => ['required', 'in:pendiente,en_curso,completada']]);
        Empleado::findOrFail($empleado)->update($data);
        return back()->with('success', 'Inducción actualizada.');
    }
}
