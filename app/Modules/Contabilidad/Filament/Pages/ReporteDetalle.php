<?php

namespace App\Modules\Contabilidad\Filament\Pages;

use App\Modules\Cartera\Enums\ClasificacionDiferencia;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Cartera\Models\PagoVenta;
use App\Modules\Dropi\Models\DropiDevolucion;
use App\Modules\Dropi\Models\GarantiaTicket;
use App\Modules\Dropi\Models\Producto;
use BackedEnum;
use Filament\Pages\Page;

class ReporteDetalle extends Page
{
    protected static ?string $slug = 'contabilidad/reporte/{slug}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'contabilidad.pages.reporte';

    public string $tipoReporte = '';

    // Filtros inteligentes (se enlazan en vivo desde la vista).
    public ?string $fechaDesde = null;
    public ?string $fechaHasta = null;
    public string $buscar = '';
    public string $filtroExtra = '';

    protected static string|BackedEnum|null $navigationIcon = null;

    /**
     * Re-audit M5 SEG-C1 · solo esContable() (Aracely/Gerencia/Gerente/Contador).
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public function mount(string $slug): void
    {
        $slugsPermitidos = ['cartera', 'arqueo', 'consignaciones', 'movimientos', 'descuentos', 'garantias', 'productos'];
        abort_unless(in_array($slug, $slugsPermitidos, true), 404);

        $this->tipoReporte = $slug;
        static::$title = $this->titulo();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['fechaDesde', 'fechaHasta', 'buscar', 'filtroExtra']);
    }

    public function titulo(): string
    {
        return match ($this->tipoReporte) {
            'cartera' => 'Cartera real por cliente',
            'arqueo' => 'Arqueo por caja',
            'consignaciones' => 'Consignaciones por aclarar',
            'movimientos' => 'Movimientos bancarios',
            'descuentos' => 'Descuentos y fletes asumidos',
            'garantias' => 'Garantías enviadas',
            'productos' => 'Productos netos / no netos',
            default => 'Reporte',
        };
    }

    /**
     * Describe qué filtros aplican a cada reporte (para pintar la barra).
     *
     * @return array{fecha:?string, buscar:?string, extra:?array{label:string, options:array<string,string>}}
     */
    public function filtrosDisponibles(): array
    {
        return match ($this->tipoReporte) {
            'cartera' => [
                'fecha' => 'Vencimiento',
                'buscar' => 'Cliente o N° de factura',
                'extra' => ['label' => 'Estado', 'options' => collect(EstadoFactura::cases())
                    ->reject(fn ($e) => in_array($e, [EstadoFactura::Pagada, EstadoFactura::Anulada], true))
                    ->mapWithKeys(fn ($e) => [$e->value => $e->label()])->all()],
            ],
            'arqueo' => [
                'fecha' => 'Fecha',
                'buscar' => 'Caja',
                'extra' => ['label' => 'Resultado', 'options' => [
                    'cuadrado' => 'Cuadrado', 'sobrante' => 'Sobrante', 'faltante' => 'Faltante',
                ]],
            ],
            'consignaciones' => ['fecha' => 'Fecha', 'buscar' => 'Cliente, banco o referencia', 'extra' => null],
            'movimientos' => ['fecha' => 'Fecha', 'buscar' => 'Cuenta PUC o descripción', 'extra' => null],
            'descuentos' => [
                'fecha' => 'Fecha',
                'buscar' => 'Cliente o N° de factura',
                'extra' => ['label' => 'Clasificación', 'options' => [
                    'descuento_pronto_pago' => 'Descuento pronto pago',
                    'descuento_fuera_plazo' => 'Descuento fuera de plazo',
                    'flete_asumido_gb' => 'Flete asumido GB',
                ]],
            ],
            'garantias' => [
                'fecha' => 'Fecha',
                'buscar' => 'Cliente o guía',
                'extra' => ['label' => 'Estado', 'options' => GarantiaTicket::query()
                    ->distinct()->orderBy('estado')->pluck('estado', 'estado')
                    ->filter()->map(fn ($e) => ucfirst((string) $e))->all()],
            ],
            'productos' => [
                'fecha' => null,
                'buscar' => 'Referencia, nombre o categoría',
                'extra' => ['label' => 'Estado', 'options' => ['1' => 'Activos', '0' => 'Inactivos']],
            ],
            default => ['fecha' => null, 'buscar' => null, 'extra' => null],
        };
    }

    public function getColumnas(): array
    {
        return match ($this->tipoReporte) {
            'cartera' => ['Cliente', 'Factura', 'Vencimiento', 'Estado', 'Total', 'Saldo'],
            'arqueo' => ['Caja', 'Fecha', 'Saldo esperado', 'Saldo contado', 'Diferencia', 'Resultado'],
            'consignaciones' => ['Fecha', 'Cliente', 'Banco', 'Referencia', 'Monto'],
            'movimientos' => ['Fecha', 'Cuenta', 'Descripción', 'Debe', 'Haber'],
            'descuentos' => ['Fecha', 'Cliente', 'Factura', 'Clasificación', 'Monto'],
            'garantias' => ['Fecha', 'Guía original', 'Cliente', 'Destino inventario', 'Costo reposición (ref.)'],
            'productos' => ['Referencia', 'Nombre', 'Categoría', 'Precio proveedor', 'Activo'],
            default => [],
        };
    }

    /** Aplica rango de fechas (whereDate) si el usuario definió alguno. */
    private function rango($q, string $col)
    {
        if (! empty($this->fechaDesde)) {
            $q->whereDate($col, '>=', $this->fechaDesde);
        }
        if (! empty($this->fechaHasta)) {
            $q->whereDate($col, '<=', $this->fechaHasta);
        }
        return $q;
    }

    private function termino(): ?string
    {
        $t = trim($this->buscar);
        return $t === '' ? null : $t;
    }

    public function getFilas(): array
    {
        $term = $this->termino();
        $extra = trim($this->filtroExtra);

        return match ($this->tipoReporte) {
            'cartera' => tap(FacturaVenta::whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])->with('contacto'), function ($q) use ($term, $extra) {
                $this->rango($q, 'fecha_vencimiento');
                if ($extra !== '') {
                    $q->where('estado', $extra);
                }
                if ($term) {
                    $q->where(fn ($s) => $s->where('numero', 'like', "%{$term}%")
                        ->orWhereHas('contacto', fn ($c) => $c->where('nombre_completo', 'like', "%{$term}%")->orWhere('razon_social', 'like', "%{$term}%")->orWhere('numero_documento', 'like', "%{$term}%")));
                }
            })->orderBy('fecha_vencimiento')->limit(500)->get()
                ->map(fn ($f) => [
                    $f->contacto?->nombreDisplay() ?? '—',
                    $f->numero,
                    $f->fecha_vencimiento->format('Y-m-d'),
                    $f->estado->label(),
                    '$' . number_format((float) $f->total, 0, ',', '.'),
                    '$' . number_format((float) $f->saldo, 0, ',', '.'),
                ])->all(),

            'arqueo' => tap(\DB::table('arqueos')->join('cajas', 'arqueos.caja_id', '=', 'cajas.id')
                ->select('cajas.nombre as caja', 'arqueos.fecha', 'arqueos.saldo_esperado', 'arqueos.saldo_contado', 'arqueos.diferencia', 'arqueos.resultado'), function ($q) use ($term, $extra) {
                    $this->rango($q, 'arqueos.fecha');
                    if ($extra !== '') {
                        $q->where('arqueos.resultado', $extra);
                    }
                    if ($term) {
                        $q->where('cajas.nombre', 'like', "%{$term}%");
                    }
                })->orderByDesc('arqueos.fecha')->limit(200)->get()
                ->map(fn ($a) => [
                    $a->caja,
                    $a->fecha,
                    '$' . number_format((float) $a->saldo_esperado, 0, ',', '.'),
                    '$' . number_format((float) $a->saldo_contado, 0, ',', '.'),
                    '$' . number_format((float) $a->diferencia, 0, ',', '.'),
                    ucfirst((string) $a->resultado),
                ])->all(),

            'consignaciones' => tap(PagoVenta::query()
                ->where(fn ($q) => $q->where('clasificacion_diferencia', ClasificacionDiferencia::NoIdentificado->value)->orWhereNull('factura_id'))
                ->with('contacto'), function ($q) use ($term) {
                    $this->rango($q, 'fecha');
                    if ($term) {
                        $q->where(fn ($s) => $s->where('banco', 'like', "%{$term}%")->orWhere('referencia', 'like', "%{$term}%")
                            ->orWhereHas('contacto', fn ($c) => $c->where('nombre_completo', 'like', "%{$term}%")->orWhere('razon_social', 'like', "%{$term}%")));
                    }
                })->orderByDesc('fecha')->limit(200)->get()
                ->map(fn ($p) => [
                    $p->fecha->format('Y-m-d'),
                    $p->contacto?->nombreDisplay() ?? '—',
                    $p->banco ?? '—',
                    $p->referencia ?? '—',
                    '$' . number_format((float) $p->monto_recibido, 0, ',', '.'),
                ])->all(),

            'movimientos' => tap(MovimientoContable::query(), function ($q) use ($term) {
                // Si no hay rango definido, por defecto el mes actual (evita traer todo el histórico).
                if (empty($this->fechaDesde) && empty($this->fechaHasta)) {
                    $q->whereBetween('fecha', [
                        now('America/Bogota')->startOfMonth()->toDateString(),
                        now('America/Bogota')->endOfMonth()->toDateString(),
                    ]);
                } else {
                    $this->rango($q, 'fecha');
                }
                if ($term) {
                    $q->where(fn ($s) => $s->where('cuenta_puc', 'like', "%{$term}%")->orWhere('descripcion', 'like', "%{$term}%"));
                }
            })->orderByDesc('fecha')->orderByDesc('id')->limit(500)->get()
                ->map(fn ($m) => [
                    $m->fecha->format('Y-m-d'),
                    $m->cuenta_puc,
                    $m->descripcion ?? '—',
                    (float) $m->debe > 0 ? '$' . number_format((float) $m->debe, 0, ',', '.') : '',
                    (float) $m->haber > 0 ? '$' . number_format((float) $m->haber, 0, ',', '.') : '',
                ])->all(),

            'descuentos' => tap(PagoVenta::query()
                ->whereIn('clasificacion_diferencia', ['descuento_pronto_pago', 'descuento_fuera_plazo', 'flete_asumido_gb'])
                ->with('contacto', 'factura'), function ($q) use ($term, $extra) {
                    $this->rango($q, 'fecha');
                    if ($extra !== '') {
                        $q->where('clasificacion_diferencia', $extra);
                    }
                    if ($term) {
                        $q->where(fn ($s) => $s->whereHas('contacto', fn ($c) => $c->where('nombre_completo', 'like', "%{$term}%")->orWhere('razon_social', 'like', "%{$term}%"))
                            ->orWhereHas('factura', fn ($fa) => $fa->where('numero', 'like', "%{$term}%")));
                    }
                })->orderByDesc('fecha')->limit(200)->get()
                ->map(fn ($p) => [
                    $p->fecha->format('Y-m-d'),
                    $p->contacto?->nombreDisplay() ?? '—',
                    $p->factura?->numero ?? '—',
                    $p->clasificacion_diferencia?->label() ?? '—',
                    '$' . number_format((float) $p->diferencia, 0, ',', '.'),
                ])->all(),

            'garantias' => tap(GarantiaTicket::query()->with('pedidoOriginal', 'variante.producto'), function ($q) use ($term, $extra) {
                $this->rango($q, 'created_at');
                if ($extra !== '') {
                    $q->where('estado', $extra);
                }
                if ($term) {
                    $q->where(fn ($s) => $s->where('cliente_nombre', 'like', "%{$term}%")
                        ->orWhereHas('pedidoOriginal', fn ($p) => $p->where('guia', 'like', "%{$term}%")));
                }
            })->orderByDesc('created_at')->limit(200)->get()
                ->map(fn ($g) => [
                    $g->created_at?->format('Y-m-d') ?? '—',
                    $g->pedidoOriginal?->guia ?? '—',
                    $g->cliente_nombre,
                    $g->estado,
                    '$' . number_format((float) ($g->variante?->producto?->precio_proveedor ?? 0), 0, ',', '.'),
                ])->all(),

            'productos' => tap(Producto::query(), function ($q) use ($term, $extra) {
                if ($extra !== '') {
                    $q->where('activo', (int) $extra);
                }
                if ($term) {
                    $q->where(fn ($s) => $s->where('referencia', 'like', "%{$term}%")->orWhere('nombre', 'like', "%{$term}%")->orWhere('categoria', 'like', "%{$term}%"));
                }
            })->orderBy('referencia')->limit(500)->get()
                ->map(fn ($p) => [
                    $p->referencia,
                    $p->nombre,
                    $p->categoria ?? '—',
                    '$' . number_format((float) $p->precio_proveedor, 0, ',', '.'),
                    $p->activo ? '✓' : '✗',
                ])->all(),

            default => [],
        };
    }
}
