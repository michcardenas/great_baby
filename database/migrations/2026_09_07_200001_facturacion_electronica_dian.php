<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación electrónica DIAN vía SIIGO.
 * - Campos de emisión (siigo_id, numero_siigo, stamp_status, siigo_response, qr_url, qr_html)
 * - Token público para descargar la factura sin auth
 * - Tabla empresas para reemplazar los hardcodes del PDF
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_venta', function (Blueprint $table) {
            $table->string('siigo_id', 60)->nullable()->after('ari_factura_id');
            $table->string('numero_siigo', 40)->nullable()->after('siigo_id');
            $table->string('stamp_status', 40)->nullable()->after('cufe');
            $table->json('siigo_response')->nullable()->after('stamp_status');
            $table->text('qr_url')->nullable()->after('siigo_response');
            $table->longText('qr_html')->nullable()->after('qr_url');
            $table->string('token_publico', 64)->nullable()->unique()->after('qr_html');
            $table->boolean('es_electronica')->default(false)->after('token_publico');
            $table->timestamp('emitida_at')->nullable()->after('es_electronica');
        });

        Schema::create('empresa_config', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 200)->nullable();
            $table->string('nit', 20)->comment('Con o sin DV, ej 901738354-7');
            $table->string('regimen', 40)->default('Responsable de IVA');
            $table->string('actividad_economica', 10)->nullable()->comment('Código CIIU');
            $table->string('logo_path', 255)->nullable();

            // Dirección fiscal
            $table->string('direccion', 200)->default('');
            $table->string('ciudad', 100)->default('');
            $table->string('departamento', 100)->default('');
            $table->string('pais', 100)->default('Colombia');
            $table->string('telefono', 40)->default('');
            $table->string('email', 100)->default('');
            $table->string('web', 100)->nullable();

            // Resolución DIAN factura electrónica de venta (nacional)
            $table->string('resolucion_dian', 40)->nullable();
            $table->date('resolucion_desde')->nullable();
            $table->date('resolucion_hasta')->nullable();
            $table->string('prefijo_dian', 10)->nullable();
            $table->unsignedInteger('rango_desde')->nullable();
            $table->unsignedInteger('rango_hasta')->nullable();

            // Banco (para B2B / exportación)
            $table->string('banco_nombre', 100)->nullable();
            $table->string('banco_swift', 20)->nullable();
            $table->string('banco_cuenta', 40)->nullable();
            $table->string('banco_iban', 60)->nullable();
            $table->string('banco_moneda', 5)->nullable();

            // Contacto financiero
            $table->string('financiero_nombre', 100)->nullable();
            $table->string('financiero_email', 100)->nullable();
            $table->string('financiero_telefono', 40)->nullable();

            $table->text('pie_pdf')->nullable()->comment('Texto legal al pie del PDF de factura');
            $table->timestamps();
        });

        // seed con los hardcodes actuales para no romper PDFs viejos
        DB::table('empresa_config')->insert([
            'razon_social' => 'GREAT BABY S.A.S.',
            'nombre_comercial' => 'Great Baby',
            'nit' => '901.738.354-7',
            'regimen' => 'Responsable de IVA',
            'direccion' => 'Bucaramanga',
            'ciudad' => 'Bucaramanga',
            'departamento' => 'Santander',
            'pais' => 'Colombia',
            'telefono' => '',
            'email' => '',
            'pie_pdf' => 'Documento generado por el ERP GREAT BABY · MYTech Solutions S.A.S.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('facturas_venta', function (Blueprint $table) {
            $table->dropColumn([
                'siigo_id', 'numero_siigo', 'stamp_status', 'siigo_response',
                'qr_url', 'qr_html', 'token_publico', 'es_electronica', 'emitida_at',
            ]);
        });
        Schema::dropIfExists('empresa_config');
    }
};
