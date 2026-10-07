<?php

namespace App\Console\Commands;

use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\NotaCredito;
use App\Modules\Cartera\Models\NotaDebito;
use App\Modules\Cartera\Models\PagoProveedor;
use App\Modules\Compras\Models\DevolucionProveedor;
use App\Modules\Compras\Models\RecepcionCompra;
use App\Modules\Contabilidad\Models\AsientoManual;
use App\Modules\Siigo\Models\SiigoSyncLog;
// Transporte real de WhatsApp del ERP. Vive en la carpeta de Dropi por
// historia, pero es el que ya usan Cartera (cobranzas) y Notificaciones.
// Antes acá se importaba `App\Services\WhatsappService`, que NO existe: el
// `class_exists()` de abajo daba false y la alerta de discrepancias con SIIGO
// se descartaba en silencio todas las madrugadas.
use App\Modules\Dropi\Services\WhatsAppClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * TEST-S7 · Comparador diario ERP ↔ SIIGO.
 *
 * Barre a diario (03:30 am) los documentos del día anterior y detecta
 * discrepancias con SIIGO. Genera un reporte en `storage/app/siigo-diario/`
 * con el formato YYYY-MM-DD.txt y, si hay >=1 problema, dispara una alerta
 * WhatsApp a Aracely + email al equipo contable (si está configurado).
 *
 * Qué compara:
 *   • Facturas de venta aprobadas sin siigo_id
 *   • Notas crédito emitidas sin siigo_id
 *   • Notas débito emitidas sin siigo_id
 *   • Pagos a proveedor sin siigo_voucher_id
 *   • Recepciones de compra confirmadas sin siigo_id
 *   • Devoluciones a proveedor confirmadas sin siigo_id
 *   • Asientos manuales aprobados sin siigo_journal_id
 *   • Errores recientes del siigo_sync_log del día
 *
 * Uso:
 *   php artisan siigo:comparador-diario            # ayer vs hoy
 *   php artisan siigo:comparador-diario --dia=2026-10-05
 *   php artisan siigo:comparador-diario --silencioso    # no dispara alertas
 */
class SiigoComparadorDiarioCommand extends Command
{
    protected $signature = 'siigo:comparador-diario
                            {--dia= : Día a comparar (YYYY-MM-DD). Default: ayer}
                            {--silencioso : No manda WhatsApp/email aunque haya hallazgos}';

    protected $description = 'TEST-S7 · Compara ERP vs SIIGO del día anterior y alerta discrepancias';

    public function handle(\App\Modules\Siigo\Services\ConciliadorFacturasSiigo $conciliador): int
    {
        $dia = $this->option('dia')
            ? Carbon::parse($this->option('dia'))->startOfDay()
            : Carbon::yesterday('America/Bogota')->startOfDay();
        $fin = $dia->copy()->endOfDay();

        $this->info("Comparando ERP ↔ SIIGO del día {$dia->toDateString()}…");

        // Blocks del reporte.
        $bloques = [
            'facturas_venta' => FacturaVenta::query()
                ->whereNull('siigo_id')
                ->whereNotIn('estado', ['borrador', 'anulada'])
                ->whereBetween('fecha_emision', [$dia->toDateString(), $fin->toDateString()])
                ->get(['id', 'numero', 'fecha_emision', 'total']),

            'notas_credito' => NotaCredito::query()
                ->whereNull('siigo_id')
                ->whereIn('estado', ['emitida', 'aceptada'])
                ->whereBetween('emitida_at', [$dia, $fin])
                ->get(['id', 'prefijo', 'numero', 'emitida_at', 'valor']),

            'notas_debito' => NotaDebito::query()
                ->whereNull('siigo_id')
                ->whereIn('estado', ['emitida', 'aceptada'])
                ->whereBetween('emitida_at', [$dia, $fin])
                ->get(['id', 'prefijo', 'numero', 'emitida_at', 'valor']),

            'pagos_proveedor' => PagoProveedor::query()
                ->whereNull('siigo_voucher_id')
                ->whereBetween('fecha', [$dia->toDateString(), $fin->toDateString()])
                ->get(['id', 'fecha', 'monto_neto', 'contacto_id']),

            'recepciones' => RecepcionCompra::query()
                ->whereNull('siigo_id')
                ->where('estado', 'confirmada')
                ->whereBetween('fecha_recepcion', [$dia->toDateString(), $fin->toDateString()])
                ->get(['id', 'numero', 'fecha_recepcion', 'total_recibido']),

            'devoluciones' => DevolucionProveedor::query()
                ->whereNull('siigo_id')
                ->where('estado', 'confirmada')
                ->whereBetween('fecha', [$dia->toDateString(), $fin->toDateString()])
                ->get(['id', 'numero', 'fecha', 'total']),

            'asientos_manuales' => AsientoManual::query()
                ->where('estado', 'aprobado')
                ->whereNull('siigo_journal_id')
                ->whereBetween('fecha', [$dia->toDateString(), $fin->toDateString()])
                ->get(['id', 'fecha', 'glosa', 'valor_total']),
        ];

        $erroresLog = SiigoSyncLog::query()
            ->where('estado', 'fallido')
            ->whereBetween('created_at', [$dia, $fin])
            ->get(['id', 'recurso', 'mensaje', 'detalle', 'created_at']);

        // Todo lo de arriba sale de preguntarle al ERP por sí mismo: encuentra
        // "nunca se envió", pero no ve una factura que esté en los dos lados con
        // importes distintos, ni una emitida directo en el portal de SIIGO. Para
        // eso hay que consultar SIIGO.
        $conciliacion = null;
        $fallaConciliacion = null;
        try {
            $conciliacion = $conciliador->conciliar($dia, $fin);
        } catch (\Throwable $e) {
            $fallaConciliacion = $e->getMessage();
            $this->warn('No se pudo conciliar contra SIIGO: '.$fallaConciliacion);
        }

        $discrepanciasRemotas = $conciliacion
            ? $conciliacion['resumen']['descuadradas']
                + $conciliacion['resumen']['ausentes_en_siigo']
                + $conciliacion['resumen']['solo_siigo']
            : 0;

        // Totales.
        $totalDiscrepancias = array_sum(array_map(fn ($c) => $c->count(), $bloques))
            + $erroresLog->count()
            + $discrepanciasRemotas;
        $totalMontoSinSiigo = 0;
        foreach ($bloques as $tipo => $coleccion) {
            foreach ($coleccion as $row) {
                $totalMontoSinSiigo += (float) ($row->total ?? $row->valor ?? $row->monto_neto ?? $row->valor_total ?? $row->total_recibido ?? 0);
            }
        }

        // Reporte texto.
        $txt = $this->componerReporte($dia, $bloques, $erroresLog, $totalDiscrepancias, $totalMontoSinSiigo, $conciliacion, $fallaConciliacion);
        $file = 'siigo-diario/' . $dia->format('Y-m-d') . '.txt';
        Storage::put($file, $txt);
        $this->line($txt);
        $this->info("Reporte guardado en storage/app/{$file}");

        Log::channel(config('logging.channels.audit') ? 'audit' : 'stack')
            ->info('siigo.comparador.diario', [
                'dia' => $dia->toDateString(),
                'total_discrepancias' => $totalDiscrepancias,
                'monto_sin_siigo' => $totalMontoSinSiigo,
                'file' => $file,
            ]);

        // Alertas.
        if ($totalDiscrepancias > 0 && ! $this->option('silencioso')) {
            $this->dispararAlertas($dia, $totalDiscrepancias, $totalMontoSinSiigo, $file);
        }

        return $totalDiscrepancias === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function componerReporte(Carbon $dia, array $bloques, $erroresLog, int $total, float $monto, ?array $conciliacion = null, ?string $fallaConciliacion = null): string
    {
        $lines = [];
        $lines[] = "═══════════════════════════════════════════════════";
        $lines[] = "  Reporte diario SIIGO ↔ ERP · {$dia->toDateString()}";
        $lines[] = "═══════════════════════════════════════════════════";
        $lines[] = "Discrepancias totales: {$total}";
        $lines[] = "Monto sin reflejar en SIIGO: $" . number_format($monto, 0, ',', '.');
        $lines[] = "";

        foreach ($bloques as $tipo => $rows) {
            if ($rows->count() === 0) continue;
            $lines[] = "── " . mb_strtoupper(str_replace('_', ' ', $tipo)) . " (" . $rows->count() . ") ──";
            foreach ($rows as $r) {
                $id = $r->numero ?? ($r->prefijo ?? '') . ($r->numero ?? "ID-{$r->id}");
                $monto = $r->total ?? $r->valor ?? $r->monto_neto ?? $r->valor_total ?? $r->total_recibido ?? 0;
                $lines[] = "  · {$id} · $" . number_format((float) $monto, 0, ',', '.');
            }
            $lines[] = "";
        }

        if ($erroresLog->count() > 0) {
            $lines[] = "── ERRORES SIIGO_SYNC_LOG (" . $erroresLog->count() . ") ──";
            foreach ($erroresLog->take(20) as $l) {
                $lines[] = "  · [{$l->recurso}] " . mb_substr((string) $l->mensaje, 0, 150);
            }
            if ($erroresLog->count() > 20) {
                $lines[] = "  … +" . ($erroresLog->count() - 20) . " más";
            }
        }

        // Lo que dice SIIGO, no el ERP.
        if ($fallaConciliacion) {
            $lines[] = "── CONCILIACIÓN CONTRA SIIGO ──";
            $lines[] = "  ⚠ No se pudo consultar SIIGO: {$fallaConciliacion}";
            $lines[] = "    Lo de arriba sólo compara el ERP consigo mismo.";
            $lines[] = "";
        } elseif ($conciliacion) {
            $r = $conciliacion['resumen'];
            $lines[] = "── CONCILIACIÓN CONTRA SIIGO ──";
            $lines[] = "  Facturas: {$r['erp']} en el ERP · {$r['siigo']} en SIIGO · {$r['coinciden']} cuadran";

            foreach ($conciliacion['descuadradas'] as $f) {
                $lines[] = "  ✗ IMPORTE DISTINTO · {$f['numero']} (SIIGO {$f['numero_siigo']}) · "
                    ."ERP $".number_format($f['total_erp'], 0, ',', '.')
                    ." vs SIIGO $".number_format($f['total_siigo'], 0, ',', '.')
                    ." · dif $".number_format($f['diferencia'], 0, ',', '.');
            }
            foreach ($conciliacion['ausentes_en_siigo'] as $f) {
                $lines[] = "  ✗ EL ERP LA DA POR EMITIDA Y SIIGO NO LA TIENE · {$f['numero']} ({$f['numero_siigo']})";
            }
            foreach (array_slice($conciliacion['solo_siigo'], 0, 10) as $f) {
                $lines[] = "  · EMITIDA FUERA DEL ERP · {$f['numero_siigo']} · $".number_format($f['total'], 0, ',', '.');
            }
            if (count($conciliacion['solo_siigo']) > 10) {
                $lines[] = "    … +".(count($conciliacion['solo_siigo']) - 10)." más";
            }
            $lines[] = "";
        }

        if ($total === 0) {
            $lines[] = $conciliacion
                ? "✓ Todo cuadra. Verificado contra SIIGO: mismos documentos y mismos importes."
                : "✓ Sin pendientes en el ERP. OJO: no se pudo verificar contra SIIGO.";
        }
        $lines[] = "";

        return implode(PHP_EOL, $lines);
    }

    private function dispararAlertas(Carbon $dia, int $total, float $monto, string $file): void
    {
        $mensaje = "⚠ SIIGO · {$dia->toDateString()}: {$total} discrepancias ($" .
            number_format($monto, 0, ',', '.') . " sin reflejar). Revisá /app/contabilidad/discrepancias-siigo";

        // WhatsApp (reusa el transporte ya existente del ERP).
        $destino = config('siigo.alerta_whatsapp')
            ?: (function_exists('setting') ? setting('siigo.alerta_whatsapp') : null);

        if ($destino) {
            try {
                $res = app(WhatsAppClient::class)->enviarTexto((string) $destino, $mensaje);
                $estado = $res['status'] ?? 'desconocido';
                $estado === 'enviado'
                    ? $this->info("Alerta WhatsApp enviada a {$destino}.")
                    : $this->warn("WhatsApp «{$estado}» para {$destino}"
                        . (isset($res['razon']) ? ": {$res['razon']}" : '')
                        . ($estado === 'mock' ? ' (driver en mock: quedó en el log, no salió)' : ''));
            } catch (\Throwable $t) {
                $this->warn("No se pudo enviar WhatsApp: " . $t->getMessage());
            }
        } else {
            $this->warn('Sin destino de WhatsApp configurado (siigo.alerta_whatsapp): alerta sólo por email.');
        }

        // Email simple (si hay destinatario configurado).
        $destEmail = config('siigo.alerta_email')
            ?: (function_exists('setting') ? setting('siigo.alerta_email') : null);
        if ($destEmail) {
            try {
                Mail::raw($mensaje . PHP_EOL . PHP_EOL . "Reporte: storage/app/{$file}",
                    fn ($m) => $m->to($destEmail)->subject("[GB-SIIGO] {$total} discrepancias · {$dia->toDateString()}"));
                $this->info("Email enviado a {$destEmail}.");
            } catch (\Throwable $t) {
                $this->warn("No se pudo enviar email: " . $t->getMessage());
            }
        }
    }
}
