<?php

namespace App\Modules\Contabilidad\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class ReportesContables extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Reportes contables';

    protected static ?string $title = 'Reportes contables';

    protected static string|\UnitEnum|null $navigationGroup = 'Contabilidad';

    protected static ?int $navigationSort = 5;

    protected string $view = 'contabilidad.pages.reportes';

    public static function canAccess(): bool
    {
        // Re-audit M5 SEG-C2 · unificado con esContable().
        return auth()->user()?->esContable() ?? false;
    }

    public function getReportes(): array
    {
        return [
            'cartera' => ['titulo' => 'Cartera real por cliente', 'descripcion' => 'Cliente, factura, vencimiento y saldo real. Cobro y validación de cupos.', 'icono' => 'heroicon-o-user-group', 'listo' => true],
            'arqueo' => ['titulo' => 'Arqueo por caja', 'descripcion' => 'Saldo físico vs saldo en sistema, por caja y responsable.', 'icono' => 'heroicon-o-banknotes', 'listo' => true],
            'consignaciones' => ['titulo' => 'Consignaciones por aclarar', 'descripcion' => 'Dinero recibido sin cliente o factura identificada. Revisión diaria.', 'icono' => 'heroicon-o-question-mark-circle', 'listo' => true],
            'movimientos' => ['titulo' => 'Movimientos bancarios', 'descripcion' => 'Libro diario de caja y bancos por periodo.', 'icono' => 'heroicon-o-arrows-right-left', 'listo' => true],
            'comisiones' => ['titulo' => 'Comisiones por vendedor', 'descripcion' => 'Base neta reconocida, % y estado. Liquidación y auditoría.', 'icono' => 'heroicon-o-users', 'listo' => false],
            'descuentos' => ['titulo' => 'Descuentos y fletes asumidos', 'descripcion' => 'Impacto financiero de la política comercial por cliente y periodo.', 'icono' => 'heroicon-o-tag', 'listo' => true],
            'prestamos' => ['titulo' => 'Préstamos a empleados', 'descripcion' => 'Vales y préstamos por tercero. Control de saldos y recuperaciones.', 'icono' => 'heroicon-o-user-circle', 'listo' => false],
            'garantias' => ['titulo' => 'Garantías enviadas', 'descripcion' => 'Reposiciones a valor $0: costo por garantía, sin cartera ni comisión.', 'icono' => 'heroicon-o-shield-check', 'listo' => true],
            'productos' => ['titulo' => 'Productos netos / no netos', 'descripcion' => 'Parametrización contable: categoría, tipo y cuentas por producto.', 'icono' => 'heroicon-o-cube', 'listo' => true],
        ];
    }
}
