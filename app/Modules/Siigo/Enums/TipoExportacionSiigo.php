<?php

namespace App\Modules\Siigo\Enums;

/**
 * Matriz de migración a SIIGO — acordada con Silvia (contabilidad) el 10-sep-2026.
 *
 * Cada tipo de documento del sistema interno se envía a SIIGO de una forma distinta,
 * según (a) si necesita mover inventario ítem por ítem y (b) si sus valores fiscales
 * cambian respecto a los reales (en cuyo caso queda como BORRADOR para que ella edite).
 *
 *  Documento                 | modo         | endpoint SIIGO | borrador
 *  --------------------------|--------------|----------------|---------
 *  Factura de venta          | electronica  | /v1/invoices   | según switch  (usar SiigoEmisionService)
 *  Compra nacional           | comprobante  | /v1/purchases  | NO  (directa; mueve inventario)
 *  Gasto operativo           | asiento      | /v1/journals   | NO  (directa; sin inventario)
 *  Importación               | comprobante  | /v1/purchases  | SÍ  (valores cambian → editar en SIIGO)
 *  Conciliación bancaria     | asiento      | /v1/journals   | SÍ  (ajustar valores en SIIGO)
 */
enum TipoExportacionSiigo: string
{
    case FacturaVenta = 'factura_venta';
    case CompraNacional = 'compra_nacional';
    case Gasto = 'gasto';
    case Importacion = 'importacion';
    case Conciliacion = 'conciliacion';

    /** 'electronica' | 'comprobante' | 'asiento' */
    public function modo(): string
    {
        return match ($this) {
            self::FacturaVenta => 'electronica',
            self::CompraNacional, self::Importacion => 'comprobante',
            self::Gasto, self::Conciliacion => 'asiento',
        };
    }

    /** Ruta del recurso en la API de SIIGO. */
    public function endpoint(): string
    {
        return match ($this->modo()) {
            'electronica' => '/v1/invoices',
            'comprobante' => '/v1/purchases',
            'asiento' => '/v1/journals',
        };
    }

    /** ¿Se deja como borrador editable en SIIGO por defecto? */
    public function borradorPorDefecto(): bool
    {
        return match ($this) {
            self::Importacion, self::Conciliacion => true,
            default => false,
        };
    }

    /**
     * Clave de `setting()` con el ID de tipo de documento en SIIGO para este recurso.
     * (Para la factura de venta el ID vive en SiigoConfig->tipo_documento_id.)
     */
    public function settingTipoDocumento(): ?string
    {
        return match ($this) {
            self::FacturaVenta => null,
            self::CompraNacional => 'siigo.doc_type_compra',
            self::Gasto => 'siigo.doc_type_gasto',
            self::Importacion => 'siigo.doc_type_importacion',
            self::Conciliacion => 'siigo.doc_type_conciliacion',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::FacturaVenta => 'Factura de venta',
            self::CompraNacional => 'Compra nacional',
            self::Gasto => 'Gasto operativo',
            self::Importacion => 'Importación',
            self::Conciliacion => 'Conciliación bancaria',
        };
    }
}
