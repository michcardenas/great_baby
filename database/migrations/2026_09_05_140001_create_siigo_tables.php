<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Config de la integración (una sola fila)
        Schema::create('siigo_config', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->text('access_key')->nullable()->comment('Encriptado con Crypt');
            $table->string('partner_id')->nullable();
            $table->string('ambiente', 20)->default('sandbox');
            $table->boolean('activo')->default(false);
            $table->string('nit_emisor', 20)->nullable();
            $table->unsignedInteger('tipo_documento_id')->nullable();
            $table->unsignedInteger('seller_id')->nullable();
            $table->unsignedInteger('payment_type_id')->nullable();
            $table->text('token_cache')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('sync_catalogos_at')->nullable();
            $table->timestamp('sync_productos_at')->nullable();
            $table->timestamp('sync_clientes_at')->nullable();
            $table->timestamp('sync_stock_at')->nullable();
            $table->timestamps();
        });

        // Catálogos cacheados desde SIIGO (document-types, taxes, payment-types)
        Schema::create('siigo_catalogos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40)->comment('document-types|taxes|payment-types|warehouses|account-groups|price-lists');
            $table->string('codigo', 60);
            $table->string('nombre', 200);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['tipo', 'codigo']);
        });

        // Bitácora de sincronizaciones
        Schema::create('siigo_sync_log', function (Blueprint $table) {
            $table->id();
            $table->string('recurso', 40)->comment('productos|warehouses|customers|stock|invoices|catalogos');
            $table->string('estado', 20)->default('exitoso')->comment('exitoso|parcial|fallido');
            $table->unsignedInteger('nuevos')->default(0);
            $table->unsignedInteger('actualizados')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->unsignedInteger('duracion_ms')->default(0);
            $table->text('mensaje')->nullable();
            $table->json('detalle')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['recurso', 'created_at']);
        });

        // Mapeo bidireccional: siigo_id en tablas propias
        Schema::table('productos', function (Blueprint $table) {
            $table->string('siigo_id', 60)->nullable()->after('referencia')->unique();
            $table->string('siigo_code', 60)->nullable()->after('siigo_id');
            $table->timestamp('siigo_sync_at')->nullable();
        });
        Schema::table('contactos', function (Blueprint $table) {
            $table->string('siigo_id', 60)->nullable()->after('numero_documento')->unique();
            $table->timestamp('siigo_sync_at')->nullable();
        });
        Schema::table('inventario_ubicaciones', function (Blueprint $table) {
            $table->string('siigo_id', 60)->nullable()->after('codigo')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_ubicaciones', fn (Blueprint $t) => $t->dropColumn('siigo_id'));
        Schema::table('contactos', fn (Blueprint $t) => $t->dropColumn(['siigo_id', 'siigo_sync_at']));
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['siigo_id', 'siigo_code', 'siigo_sync_at']));
        Schema::dropIfExists('siigo_sync_log');
        Schema::dropIfExists('siigo_catalogos');
        Schema::dropIfExists('siigo_config');
    }
};
