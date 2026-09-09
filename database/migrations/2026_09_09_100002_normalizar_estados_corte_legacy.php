<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P6 · Antes existían estados intermedios (alistando/empacando/despachado) que
 * nunca se seteaban en el flujo real pero sí quedaron en la BD por seeders
 * viejos. Al limpiar el enum a {abierto, cerrado} esas filas explotan cuando
 * Eloquent hace EstadoCorte::from('despachado').
 *
 * Migración de datos: cualquier estado que NO sea 'cerrado' pasa a 'abierto'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('dropi_cortes')
            ->whereNotIn('estado', ['abierto', 'cerrado'])
            ->update(['estado' => 'abierto']);
    }

    public function down(): void
    {
        // No hay vuelta atrás — los estados intermedios eran código muerto.
    }
};
