<?php

namespace App\Modules\Contabilidad\Filament\Pages;

use App\Modules\Cartera\Models\MovimientoContable;
use BackedEnum;
use Filament\Pages\Page;

/**
 * Balance General (situación financiera) a una fecha de corte, desde los asientos.
 * Clases PUC: 1=Activo (débito), 2=Pasivo (crédito), 3=Patrimonio (crédito).
 * El resultado del ejercicio (4-5-6 acumulado) se suma al patrimonio para cuadrar
 * la ecuación Activo = Pasivo + Patrimonio.
 */
class BalanceGeneral extends Page
{
    protected static ?string $slug = 'contabilidad/balance-general';

    protected string $view = 'contabilidad.pages.balance-general';

    protected static ?string $navigationLabel = 'Balance general';

    protected static ?string $title = 'Balance General';

    protected static string|\UnitEnum|null $navigationGroup = 'Contabilidad';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    public ?string $corte = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->esContable() ?? false;
    }

    public function mount(): void
    {
        $this->corte = $this->corte ?? now('America/Bogota')->toDateString();
    }

    /**
     * @return array<string,mixed>
     */
    public function datos(): array
    {
        $corte = $this->corte ?: now('America/Bogota')->toDateString();

        $filas = MovimientoContable::query()
            ->whereDate('fecha', '<=', $corte)
            ->selectRaw('cuenta_puc, LEFT(cuenta_puc,1) AS clase, SUM(debe) AS d, SUM(haber) AS h')
            ->groupBy('cuenta_puc')
            ->orderBy('cuenta_puc')
            ->get();

        $activo = [];
        $pasivo = [];
        $patrimonio = [];
        $totActivo = 0.0;
        $totPasivo = 0.0;
        $totPatrimonio = 0.0;
        $ingresos = 0.0;
        $costosGastos = 0.0;

        foreach ($filas as $f) {
            $d = (float) $f->d;
            $h = (float) $f->h;
            switch ($f->clase) {
                case '1': // activo — débito
                    $val = $d - $h;
                    if (abs($val) < 0.01) break;
                    $activo[] = ['cuenta' => $f->cuenta_puc, 'valor' => $val];
                    $totActivo += $val;
                    break;
                case '2': // pasivo — crédito
                    $val = $h - $d;
                    if (abs($val) < 0.01) break;
                    $pasivo[] = ['cuenta' => $f->cuenta_puc, 'valor' => $val];
                    $totPasivo += $val;
                    break;
                case '3': // patrimonio — crédito
                    $val = $h - $d;
                    if (abs($val) < 0.01) break;
                    $patrimonio[] = ['cuenta' => $f->cuenta_puc, 'valor' => $val];
                    $totPatrimonio += $val;
                    break;
                case '4': // ingresos
                    $ingresos += $h - $d;
                    break;
                case '5': // gastos
                case '6': // costos
                    $costosGastos += $d - $h;
                    break;
            }
        }

        $resultadoEjercicio = $ingresos - $costosGastos;
        $totalPasivoPatrimonio = $totPasivo + $totPatrimonio + $resultadoEjercicio;
        $descuadre = round($totActivo - $totalPasivoPatrimonio, 2);

        return [
            'corte' => $corte,
            'activo' => $activo,
            'pasivo' => $pasivo,
            'patrimonio' => $patrimonio,
            'totActivo' => $totActivo,
            'totPasivo' => $totPasivo,
            'totPatrimonio' => $totPatrimonio,
            'resultadoEjercicio' => $resultadoEjercicio,
            'totalPasivoPatrimonio' => $totalPasivoPatrimonio,
            'descuadre' => $descuadre,
            'cuadra' => abs($descuadre) < 0.01,
        ];
    }
}
