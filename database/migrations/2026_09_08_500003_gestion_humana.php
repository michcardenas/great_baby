<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vacantes
        Schema::create('rrhh_vacantes', function (Blueprint $t) {
            $t->id();
            $t->string('titulo');
            $t->string('area', 50);
            $t->text('descripcion')->nullable();
            $t->text('requisitos')->nullable();
            $t->decimal('salario_min', 12, 2)->nullable();
            $t->decimal('salario_max', 12, 2)->nullable();
            $t->enum('modalidad', ['presencial', 'remoto', 'hibrido'])->default('presencial');
            $t->enum('tipo_contrato', ['indefinido', 'obra_labor', 'prestacion_servicios', 'aprendizaje', 'temporal'])->default('indefinido');
            $t->enum('estado', ['abierta', 'en_seleccion', 'cerrada'])->default('abierta');
            $t->date('fecha_apertura')->nullable();
            $t->date('fecha_cierre')->nullable();
            $t->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['estado', 'area']);
        });

        // Candidatos
        Schema::create('rrhh_candidatos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vacante_id')->constrained('rrhh_vacantes')->cascadeOnDelete();
            $t->string('nombre');
            $t->string('email', 150)->nullable();
            $t->string('telefono', 30)->nullable();
            $t->string('cv_path')->nullable();
            $t->enum('etapa', ['nuevo', 'revision', 'entrevista', 'prueba', 'oferta', 'contratado', 'descartado'])->default('nuevo');
            $t->tinyInteger('calificacion')->nullable(); // 1-5
            $t->text('notas')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['vacante_id', 'etapa']);
        });

        // Empleados (registro simple - nómina NO está en base)
        Schema::create('rrhh_empleados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('candidato_id')->nullable()->constrained('rrhh_candidatos')->nullOnDelete();
            $t->string('nombre');
            $t->string('tipo_documento', 5)->default('CC');
            $t->string('numero_documento', 20)->unique();
            $t->string('email', 150)->nullable();
            $t->string('telefono', 30)->nullable();
            $t->string('cargo', 100);
            $t->string('area', 50);
            $t->enum('tipo_contrato', ['indefinido', 'obra_labor', 'prestacion_servicios', 'aprendizaje', 'temporal']);
            $t->decimal('salario', 12, 2)->nullable();
            $t->date('fecha_ingreso');
            $t->date('fecha_retiro')->nullable();
            $t->enum('estado', ['activo', 'retirado', 'suspendido'])->default('activo');
            $t->enum('estado_induccion', ['pendiente', 'en_curso', 'completada'])->default('pendiente');
            $t->text('notas')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        // Docs empleado (contrato, cédula, EPS, ARL, pensiones, etc)
        Schema::create('rrhh_documentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empleado_id')->constrained('rrhh_empleados')->cascadeOnDelete();
            $t->string('tipo', 60);
            $t->string('nombre');
            $t->string('archivo_path');
            $t->date('fecha_vencimiento')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rrhh_documentos');
        Schema::dropIfExists('rrhh_empleados');
        Schema::dropIfExists('rrhh_candidatos');
        Schema::dropIfExists('rrhh_vacantes');
    }
};
