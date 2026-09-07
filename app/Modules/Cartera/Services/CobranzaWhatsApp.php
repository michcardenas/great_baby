<?php

namespace App\Modules\Cartera\Services;

use App\Modules\Cartera\Enums\TramoAntiguedad;
use App\Modules\Cartera\Models\CobranzaRegistro;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Dropi\Services\WhatsAppClient;
use Illuminate\Support\Facades\Log;

/**
 * §7 TO-BE Cartera — Cobranza automatizada por antigüedad.
 * Mensajes escalan según tramo, con recordatorio suave al inicio y escalamiento a Gerencia en 120+.
 * Escribe siempre en cobranzas_registro para tener bitácora.
 * Envío REAL vía WhatsApp Cloud API (driver `mock` en local, `cloud` en prod).
 */
class CobranzaWhatsApp
{
    public function __construct(protected WhatsAppClient $wa) {}

    public function ejecutarBarrido(): array
    {
        $stats = ['procesadas' => 0, 'enviadas' => 0, 'sin_telefono' => 0, 'escaladas' => 0];

        $facturas = FacturaVenta::query()
            ->whereRaw('saldo > 0')
            ->whereIn('estado', ['pendiente', 'abonada', 'vencida'])
            ->with('contacto')
            ->get();

        foreach ($facturas as $f) {
            $stats['procesadas']++;

            if (! $f->contacto?->telefono) { $stats['sin_telefono']++; continue; }

            $tramo = $f->tramo();
            if ($tramo === TramoAntiguedad::AlDia) continue; // no cobrar al día

            // Evitar spam: máximo 1 mensaje del mismo tramo por factura en 3 días
            $ultima = CobranzaRegistro::query()
                ->where('factura_id', $f->id)
                ->where('tramo', $tramo->value)
                ->where('enviado_at', '>=', now()->subDays(3))
                ->first();
            if ($ultima) continue;

            $mensaje = $this->componer($tramo, $f);
            $resp = $this->wa->enviarTexto($f->contacto->telefono, $mensaje);

            // Log solo lo esencial (no PII completa — fix seguridad L2)
            Log::info('[Cobranza WA] intento', [
                'factura_id' => $f->id, 'tramo' => $tramo->value, 'status' => $resp['status'] ?? 'unknown',
            ]);

            $estadoRegistro = in_array($resp['status'] ?? '', ['enviado', 'mock']) ? 'enviado' : 'error';

            CobranzaRegistro::create([
                'factura_id' => $f->id,
                'contacto_id' => $f->contacto_id,
                'canal' => 'whatsapp',
                'tramo' => $tramo->value,
                'estado' => $estadoRegistro,
                'mensaje' => $mensaje,
                'respuesta_api' => $resp,
                'enviado_at' => now(),
            ]);

            if ($estadoRegistro === 'enviado') $stats['enviadas']++;
            if ($tramo === TramoAntiguedad::D120Mas) $stats['escaladas']++;
        }

        return $stats;
    }

    protected function componer(TramoAntiguedad $tramo, FacturaVenta $f): string
    {
        $nombre = $f->contacto->nombreDisplay();
        $numero = $f->numero;
        $saldo = '$' . number_format((float) $f->saldo, 0, ',', '.');
        $mora = $f->diasMora();

        return match ($tramo) {
            TramoAntiguedad::D0_30 => "Hola {$nombre} 👋 Te recordamos que la factura {$numero} está vencida por {$mora} días. Saldo: {$saldo}. GREAT BABY",
            TramoAntiguedad::D31_59 => "Hola {$nombre}, la factura {$numero} lleva {$mora} días vencida. Por favor coordinemos el pago. Saldo: {$saldo}. GREAT BABY",
            TramoAntiguedad::D60_89 => "⚠️ {$nombre}, la factura {$numero} suma {$mora} días de mora. Estamos escalando la gestión. Saldo: {$saldo}. GREAT BABY",
            TramoAntiguedad::D90_119 => "🚨 {$nombre}, la factura {$numero} está en 90+ días de mora. Debemos evaluar suspensión de crédito. Saldo: {$saldo}. Contacta a Cartera.",
            TramoAntiguedad::D120Mas => "🚨 {$nombre}, la factura {$numero} lleva {$mora} días vencida. Se escala a Gerencia. Saldo: {$saldo}. GREAT BABY",
            default => "Recordatorio de pago — {$numero} · {$saldo}",
        };
    }
}
