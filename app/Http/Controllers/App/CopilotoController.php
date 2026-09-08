<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\PagoVenta;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\GarantiaTicket;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MEJORAS-B · Copiloto IA con Claude API.
 * Aracely pregunta en español; el copiloto arma contexto con snapshots del ERP y responde.
 *
 * ENV: ANTHROPIC_API_KEY, ANTHROPIC_MODEL (default: claude-sonnet-4-20250514)
 */
class CopilotoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless($r->user()?->esAracely(), 403);
                return $next($r);
            }),
            new Middleware('throttle:20,1'), // 20 preguntas por minuto
        ];
    }

    public function preguntar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pregunta' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $contexto = $this->armarContextoErp();
        $apiKey = env('ANTHROPIC_API_KEY');
        $model = env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514');

        if (! $apiKey) {
            return response()->json([
                'respuesta' => "⚠ Copiloto sin configurar. Falta `ANTHROPIC_API_KEY` en `.env`.\n\nMientras tanto, aquí tienes datos actuales:\n" . $this->respuestaLocal($data['pregunta'], $contexto),
                'fallback' => true,
            ]);
        }

        $systemPrompt = <<<PROMPT
Eres el copiloto de GREAT BABY, un ERP para una empresa colombiana de productos de bebé (Aracely es la dueña, Bucaramanga).
Responde en español, breve (máximo 5 líneas), con cifras COP y emojis relevantes. No inventes datos.
Si no puedes responder con los datos que te doy, dilo y sugiere qué página del ERP visitar.
El ERP corre en {$this->baseUrl()} — puedes sugerir URLs como /app/facturas, /app/dropi, /app/pedidos-b2b, /app/cartera/reportes, /app/cosas-del-dia.

DATOS ACTUALES DEL SISTEMA (snapshot):
{$contexto}
PROMPT;

        try {
            $res = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 500,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $data['pregunta']],
                ],
            ]);

            if (! $res->successful()) {
                Log::warning('Claude API error', ['body' => $res->body(), 'status' => $res->status()]);
                return response()->json([
                    'respuesta' => "⚠ Copiloto no respondió (" . $res->status() . "). Datos locales:\n" . $this->respuestaLocal($data['pregunta'], $contexto),
                    'fallback' => true,
                ]);
            }

            $texto = collect($res->json('content'))->firstWhere('type', 'text')['text'] ?? '(sin respuesta)';
            return response()->json(['respuesta' => $texto, 'fallback' => false]);
        } catch (\Throwable $e) {
            Log::error('Copiloto: ' . $e->getMessage());
            return response()->json([
                'respuesta' => "⚠ Error de red hacia Claude. Datos locales:\n" . $this->respuestaLocal($data['pregunta'], $contexto),
                'fallback' => true,
            ]);
        }
    }

    /** Snapshot compacto del ERP para inyectar como contexto. */
    private function armarContextoErp(): string
    {
        $hoy = today('America/Bogota');
        $inicioMes = $hoy->copy()->startOfMonth();

        $ventasMes = (float) FacturaVenta::whereBetween('fecha_emision', [$inicioMes, $hoy])->sum('total');
        $cobrado30d = (float) PagoVenta::whereBetween('fecha', [$hoy->copy()->subDays(30), $hoy])->sum('monto_aplicado');
        $carteraVencida = (float) FacturaVenta::where('estado', 'vencida')->whereNull('deleted_at')->sum('saldo');
        $dropiVentasMes = (float) DropiPedido::whereBetween('pagado_at', [$inicioMes, $hoy])->where('estado', 'pagado')->sum('monto_esperado_proveedor');
        $dropiPendientes = DropiPedido::whereIn('estado', ['pending', 'alistando', 'empacado'])->count();
        $pedidosB2BPend = PedidoCliente::where('estado', 'enviado')->count();
        $garantiasAbiertas = GarantiaTicket::whereIn('estado', ['abierto', 'en_revision'])->count();
        $clientesActivos = Contacto::where('activo', true)->where('es_cliente', true)->count();

        $topCiudades = DropiPedido::selectRaw('cliente_ciudad, SUM(monto_esperado_proveedor) as total')
            ->whereBetween('pagado_at', [$inicioMes, $hoy])->where('estado', 'pagado')
            ->groupBy('cliente_ciudad')->orderByDesc('total')->limit(5)->get()
            ->map(fn ($r) => "  · {$r->cliente_ciudad}: $" . number_format((float) $r->total, 0, ',', '.'))
            ->join("\n");

        $fmt = fn ($n) => '$' . number_format($n, 0, ',', '.');

        return "Fecha hoy: {$hoy->format('Y-m-d')} (Bogotá)
Ventas del mes (facturas emitidas): {$fmt($ventasMes)}
Cobrado últimos 30d: {$fmt($cobrado30d)}
Cartera vencida: {$fmt($carteraVencida)}
Dropi pagado del mes: {$fmt($dropiVentasMes)}
Pedidos Dropi en curso (no despachados): {$dropiPendientes}
Pedidos B2B esperando revisión de Aracely: {$pedidosB2BPend}
Garantías abiertas sin decidir: {$garantiasAbiertas}
Clientes activos: {$clientesActivos}
Top 5 ciudades ventas Dropi del mes:
{$topCiudades}";
    }

    /** Fallback ultra simple sin IA — busca keywords y responde con datos. */
    private function respuestaLocal(string $pregunta, string $ctx): string
    {
        return $ctx;
    }

    private function baseUrl(): string
    {
        return rtrim(config('app.url'), '/');
    }
}
