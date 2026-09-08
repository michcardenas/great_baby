<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos_operativos', function (Blueprint $t) {
            $t->id();
            $t->string('numero', 30)->unique();
            $t->date('fecha');
            $t->string('categoria', 60); // servicios_publicos, arriendo, transporte, viaticos, papeleria, etc.
            $t->string('descripcion');
            $t->decimal('monto', 12, 2);
            $t->string('proveedor', 200)->nullable();
            $t->string('factura_ref', 100)->nullable();
            $t->enum('metodo_pago', ['efectivo', 'transferencia', 'tarjeta', 'nequi', 'daviplata'])->default('transferencia');
            $t->enum('tipo', ['gasto', 'reembolso'])->default('gasto');
            $t->foreignId('solicita_id')->constrained('users');
            $t->foreignId('aprueba_id')->nullable()->constrained('users');
            $t->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'pagado'])->default('pendiente');
            $t->text('notas')->nullable();
            $t->json('adjuntos')->nullable(); // paths
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tipo', 'estado', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos_operativos');
    }
};
