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

    protected static string|BackedEnum|null $navigationIcon = null;

    /**
     * Re-audit M5 SEG-C1 · sin canAccess() cualquier User con acceso al panel
     * (Alistador, ServicioCliente) navegaba directo a la URL y veía:
     *   /reporte/cartera → saldos por cliente
     *   /reporte/productos → margen bruto (precio_proveedor)
     *   /reporte/arqueo → diferencias de caja
     * Autorización explícita: solo esContable() (Aracely/Gerencia/Gerente/Contador).
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public function mount(string $slug): void
    {
        // Re-audit M5 SEG-C1 · guard adicional en mount: whitelist explícita
        // del slug. Sin esto, si el `canAccess` se afloja a futuro, cualquier
        // slug arbitrario pasa a `getFilas()` (que lo maneja con `default => []`
        // pero es defensa en profundidad).
        $slugsPermitidos = ['cartera', 'arqueo', 'consignaciones', 'movimientos', 'descuentos', 'garantias', 'productos'];
        abort_unless(in_array($slug, $slugsPermitidos, true), 404);

        $this->tipoReporte = $slug;
        static::$title = $this->titulo();
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

    public function getFilas(): array
    {
        return match ($this->tipoReporte) {
            // Re-audit M5 DATOS-A2 · limit 500 (antes ->get() sin límite → OOM
            // en 3-6 meses de operación real; todos los demás casos limitan).
            'cartera' => FacturaVenta::whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])
                ->with('contacto')->orderBy('fecha_vencimiento')->limit(500)->get()
                ->map(fn ($f) => [
                    $f->contacto?->nombreDisplay() ?? '—',
                    $f->numero,
                    $f->fecha_vencimiento->format('Y-m-d'),
                    $f->estado->label(),
                    '$' . number_format((float) $f->total, 0, ',', '.'),
                    '$' . number_format((float) $f->saldo, 0, ',', '.'),
                ])->all(),

            'arqueo' => \DB::table('arqueos')
                ->join('cajas', 'arqueos.caja_id', '=', 'cajas.id')
                ->select('cajas.nombre as caja', 'arqueos.fecha', 'arqueos.saldo_esperado', 'arqueos.saldo_contado', 'arqueos.diferencia', 'arqueos.resultado')
                ->orderByDesc('arqueos.fecha')->limit(50)->get()
                ->map(fn ($a) => [
                    $a->caja,
                    $a->fecha,
                    '$' . number_format((float) $a->saldo_esperado, 0, ',', '.'),
                    '$' . number_format((float) $a->saldo_contado, 0, ',', '.'),
                    '$' . number_format((float) $a->diferencia, 0, ',', '.'),
                    ucfirst($a->resultado),
                ])->all(),

            'consignaciones' => PagoVenta::query()
                ->where(fn ($q) => $q->where('clasificacion_diferencia', ClasificacionDiferencia::NoIdentificado->value)->orWhereNull('factura_id'))
                ->with('contacto')->orderByDesc('fecha')->limit(50)->get()
                ->map(fn ($p) => [
                    $p->fecha->format('Y-m-d'),
                    $p->contacto?->nombreDisplay() ?? '—',
                    $p->banco ?? '—',
                    $p->referencia ?? '—',
                    '$' . number_format((float) $p->monto_recibido, 0, ',', '.'),
                ])->all(),

            // Re-audit M5 FUNC-A3 + DATOS-B2 · filtro fecha por defecto al mes
            // actual + tie-breaker por id para orden estable. Antes: sin filtro
            // fecha, imposible ver ayer si hoy hubo 100 asientos.
            'movimientos' => MovimientoContable::query()
                ->whereBetween('fecha', [
                    now('America/Bogota')->startOfMonth()->toDateString(),
                    now('America/Bogota')->endOfMonth()->toDateString(),
                ])
                ->orderByDesc('fecha')->orderByDesc('id')->limit(200)->get()
                ->map(fn ($m) => [
                    $m->fecha->format('Y-m-d'),
                    $m->cuenta_puc,
                    $m->descripcion ?? '—',
                    (float) $m->debe > 0 ? '$' . number_format((float) $m->debe, 0, ',', '.') : '',
                    (float) $m->haber > 0 ? '$' . number_format((float) $m->haber, 0, ',', '.') : '',
                ])->all(),

            'descuentos' => PagoVenta::query()
                ->whereIn('clasificacion_diferencia', ['descuento_pronto_pago', 'descuento_fuera_plazo', 'flete_asumido_gb'])
                ->with('contacto', 'factura')->orderByDesc('fecha')->limit(100)->get()
                ->map(fn ($p) => [
                    $p->fecha->format('Y-m-d'),
                    $p->contacto?->nombreDisplay() ?? '—',
                    $p->factura?->numero ?? '—',
                    $p->clasificacion_diferencia?->label() ?? '—',
                    '$' . number_format((float) $p->diferencia, 0, ',', '.'),
                ])->all(),

            'garantias' => GarantiaTicket::query()->with('pedidoOriginal', 'variante.producto')
                ->orderByDesc('created_at')->limit(50)->get()
                ->map(fn ($g) => [
                    $g->created_at?->format('Y-m-d') ?? '—',
                    $g->pedidoOriginal?->guia ?? '—',
                    $g->cliente_nombre,
                    $g->estado,
                    '$' . number_format((float) ($g->variante?->producto?->precio_proveedor ?? 0), 0, ',', '.'),
                ])->all(),

            'productos' => Producto::query()->orderBy('referencia')->limit(200)->get()
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
