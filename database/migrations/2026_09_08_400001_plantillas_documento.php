<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de documento editables por Aracely (WYSIWYG):
 *  - Factura de venta, Orden de compra, Recepción, Nota crédito, Cotización, Comprobante egreso
 * Config JSON con logo, colores, encabezado extra, pie, bloques on/off, términos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_documento', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('tipo'); // factura | oc | recepcion | nc | cotizacion | egreso
            $t->boolean('predeterminada')->default(false);
            $t->boolean('activa')->default(true);
            $t->json('config'); // ver esquema abajo
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tipo', 'predeterminada', 'activa'], 'plant_tipo_pred_activa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_documento');
    }
};

/*
 * Esquema config JSON:
 * {
 *   "colores": { "primario": "#b45309", "secundario": "#78350f", "texto": "#111", "acento": "#fef3c7" },
 *   "tipografia": "sans", // sans | serif | mono
 *   "layout": "espacioso", // espacioso | compacto
 *   "logo_url": null,          // URL pública (assets/uploads) o data URI
 *   "logo_alto_px": 60,
 *   "encabezado_extra": "",    // markdown-lite / plain
 *   "encabezado_alineacion": "izquierda", // izquierda | centrado | derecha
 *   "mostrar_qr": true,
 *   "mostrar_bloque_banco": true,
 *   "mostrar_bloque_retenciones": true,
 *   "mostrar_bloque_notas": true,
 *   "mostrar_totales_en_letras": false,
 *   "pie_html": "",             // texto/HTML simple
 *   "terminos_condiciones": "",
 *   "sello_texto": "",          // ej "PAGADA" en marca de agua
 *   "watermark_activo": false
 * }
 */
