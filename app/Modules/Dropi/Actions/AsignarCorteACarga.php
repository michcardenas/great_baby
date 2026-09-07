<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Enums\EstadoCorte;
use App\Modules\Dropi\Models\DropiCorte;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * §5 Diseño Dropi — Asigna el corte correcto a un pedido según la hora de creación.
 * Corte 1: 00:00–14:00. Corte 2: 14:00–23:59.
 * Si no existe el corte, lo crea abierto.
 */
class AsignarCorteACarga
{
    use AsAction;

    public function handle(CarbonImmutable $creadoAt): DropiCorte
    {
        $fecha = $creadoAt->toDateString();
        $numero = $creadoAt->hour < 14 ? 1 : 2;

        // firstOrCreate es atómico en MySQL/MariaDB (INSERT ... ON DUPLICATE KEY)
        // y respeta el UNIQUE (fecha, numero) evitando la race entre syncs simultáneos.
        // Fallback whereDate para el caso SQLite en tests (formato date TEXT).
        try {
            return DropiCorte::firstOrCreate(
                ['fecha' => $fecha, 'numero' => $numero],
                ['estado' => EstadoCorte::Abierto],
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race resuelta por el índice único: recuperamos el ganador.
            return DropiCorte::whereDate('fecha', $fecha)->where('numero', $numero)->firstOrFail();
        }
    }
}
