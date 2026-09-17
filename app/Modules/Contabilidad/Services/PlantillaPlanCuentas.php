<?php

namespace App\Modules\Contabilidad\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera la plantilla Excel (.xlsx) para cargar el plan de cuentas:
 *   Hoja 1 "Plan de cuentas" — encabezado estilizado + filas de ejemplo.
 *   Hoja 2 "Instrucciones"   — qué va en cada columna y valores permitidos.
 * Devuelve la ruta de un archivo temporal (el llamador lo entrega y lo borra).
 */
class PlantillaPlanCuentas
{
    private const AZUL = '1E3A5F';   // encabezado (navy)
    private const AMBAR = 'F5A623';  // marca GREAT BABY
    private const GRIS_SUAVE = 'F2F4F7';

    public function generar(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'puc_') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($ruta);

        $this->hojaPlantilla($writer);
        $this->hojaInstrucciones($writer);

        $writer->close();

        return $ruta;
    }

    private function hojaPlantilla(Writer $writer): void
    {
        $hoja = $writer->getCurrentSheet();
        $hoja->setName('Plan de cuentas');
        $hoja->setColumnWidth(16, 1);  // codigo
        $hoja->setColumnWidth(48, 2);  // nombre
        $hoja->setColumnWidth(16, 3);  // naturaleza
        $hoja->setColumnWidth(22, 4);  // permite_movimiento
        $hoja->setColumnWidth(20, 5);  // siigo_cuenta_id
        $hoja->setColumnWidth(12, 6);  // activa

        $header = (new Style())
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(self::AZUL)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setShouldWrapText(false);

        $writer->addRow(Row::fromValues(
            ['codigo', 'nombre', 'naturaleza', 'permite_movimiento', 'siigo_cuenta_id', 'activa'],
            $header
        ));

        $ejemplo = (new Style())->setBackgroundColor(self::GRIS_SUAVE);

        $filas = [
            ['1', 'ACTIVO', 'debito', 'no', '', 'si'],
            ['11', 'DISPONIBLE', 'debito', 'no', '', 'si'],
            ['1105', 'CAJA', 'debito', 'no', '', 'si'],
            ['110505', 'Caja general', 'debito', 'si', '', 'si'],
            ['1110', 'BANCOS', 'debito', 'no', '', 'si'],
            ['111005', 'Bancolombia cuenta de ahorros', 'debito', 'si', '', 'si'],
            ['13', 'DEUDORES', 'debito', 'no', '', 'si'],
            ['130505', 'Clientes nacionales', 'debito', 'si', '', 'si'],
            ['4', 'INGRESOS', 'credito', 'no', '', 'si'],
            ['4135', 'Comercio al por mayor y al por menor', 'credito', 'si', '', 'si'],
            ['5', 'GASTOS', 'debito', 'no', '', 'si'],
            ['5195', 'Diversos', 'debito', 'si', '', 'si'],
            ['6', 'COSTOS DE VENTAS', 'debito', 'no', '', 'si'],
            ['6135', 'Comercio al por mayor y al por menor', 'debito', 'si', '', 'si'],
        ];

        foreach ($filas as $f) {
            $writer->addRow(Row::fromValues($f, $ejemplo));
        }
    }

    private function hojaInstrucciones(Writer $writer): void
    {
        $hoja = $writer->addNewSheetAndMakeItCurrent();
        $hoja->setName('Instrucciones');
        $hoja->setColumnWidth(22, 1);
        $hoja->setColumnWidth(14, 2);
        $hoja->setColumnWidth(40, 3);
        $hoja->setColumnWidth(48, 4);

        $titulo = (new Style())->setFontBold()->setFontSize(15)->setFontColor(self::AZUL);
        $writer->addRow(Row::fromValues(['Cómo llenar la plantilla del Plan de Cuentas'], $titulo));

        $nota = (new Style())->setFontItalic()->setFontColor('6B7280');
        $writer->addRow(Row::fromValues(['Borra las filas de ejemplo de la hoja "Plan de cuentas" y escribe las tuyas. Se importa por CÓDIGO: si ya existe, se actualiza (no se duplica).'], $nota));
        $writer->addRow(Row::fromValues(['']));

        $header = (new Style())
            ->setFontBold()->setFontColor(Color::WHITE)
            ->setBackgroundColor(self::AMBAR)
            ->setCellAlignment(CellAlignment::CENTER);
        $writer->addRow(Row::fromValues(['Columna', 'Obligatoria', 'Valores permitidos', 'Descripción'], $header));

        $celdas = (new Style())->setShouldWrapText(true)->setCellVerticalAlignment('top');
        $definiciones = [
            ['codigo', 'Sí', 'Solo dígitos (1 a 10)', 'Código PUC. El 1er dígito define la clase: 1=Activo 2=Pasivo 3=Patrimonio 4=Ingresos 5=Gastos 6=Costo ventas. La clase, el nivel y la naturaleza se calculan solos.'],
            ['nombre', 'Sí', 'Texto', 'Nombre de la cuenta. Ej: Caja general, Bancolombia cuenta de ahorros.'],
            ['naturaleza', 'No', 'debito / credito (o vacío)', 'Déjala vacía y el sistema la calcula por la clase (Activo/Gasto/Costo = débito; Pasivo/Patrimonio/Ingreso = crédito). Solo llénala si necesitas forzarla.'],
            ['permite_movimiento', 'No', 'si / no (o vacío)', 'si = cuenta de detalle que recibe asientos. no = cuenta mayor que solo agrupa. Si la dejas vacía, se infiere: es "si" cuando ninguna otra cuenta cuelga de ella.'],
            ['siigo_cuenta_id', 'No', 'Texto', 'Código o ID de la cuenta equivalente en SIIGO (para el mapeo fiscal). Opcional.'],
            ['activa', 'No', 'si / no (o vacío)', 'si = disponible para usar (por defecto). no = archivada.'],
        ];
        foreach ($definiciones as $d) {
            $writer->addRow(Row::fromValues($d, $celdas));
        }
    }
}
