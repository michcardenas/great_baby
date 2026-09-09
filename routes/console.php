<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// §6 diseño Dropi — libera locks vencidos del alistador cada 2 min.
Schedule::command('dropi:liberar-locks')->everyTwoMinutes()->withoutOverlapping();

// F15 · re-matcheo periódico de wallet huérfanos (movimientos que llegaron antes
// de que el pedido apareciera en el sync).
Schedule::command('dropi:reconciliar-huerfanos')->everyThirtyMinutes()->withoutOverlapping();

// §7 TO-BE Cartera — barrido diario de cobranza WhatsApp a las 9:00.
Schedule::command('cartera:cobrar')->dailyAt('09:00')->withoutOverlapping();

// SIIGO — sync inventario/productos cada hora, clientes cada 3 horas.
Schedule::command('siigo:sync productos')->hourly()->withoutOverlapping();
Schedule::command('siigo:sync clientes')->everyThreeHours()->withoutOverlapping();
Schedule::command('siigo:sync catalogos')->dailyAt('03:00')->withoutOverlapping();

// M3 Inventario — barrido cada 15 min (alertas de stock + reservas expiradas).
Schedule::command('inventario:barrer')->everyFifteenMinutes()->withoutOverlapping();

// Empaque — cierra registros en_curso abandonados (> 30 min sin actividad).
Schedule::command('empaque:cerrar-huerfanos')->everyFifteenMinutes()->withoutOverlapping();

// Backup nocturno: BD (mysqldump) + fotos empaque (tar.gz), retención 14 días.
Schedule::command('gb:backup --keep=14')->dailyAt('02:00')->withoutOverlapping();

// MEJORAS-A · Reporte semanal WhatsApp a Aracely los lunes 8am (Bogotá).
Schedule::command('reporte:semanal')->weeklyOn(1, '08:00')->timezone('America/Bogota')->withoutOverlapping();

// M1 · Segmentación de clientes — semanal domingos 04:00
Schedule::command('crm:segmentar-clientes')->weeklyOn(0, '04:00')->withoutOverlapping();

// Retención fotos empaque (Habeas Data) — diario 03:30
Schedule::command('gb:purgar-fotos-empaque')->dailyAt('03:30')->withoutOverlapping();

// Cuando llegue la API de Dropi (§26) → activar sync automático:
// Schedule::command('dropi:sync --horas=1')->everyFiveMinutes()->withoutOverlapping();
