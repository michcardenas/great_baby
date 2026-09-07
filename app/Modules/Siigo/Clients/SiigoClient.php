<?php

namespace App\Modules\Siigo\Clients;

use App\Modules\Siigo\Models\SiigoConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente HTTP para la API oficial de SIIGO.
 *
 * Reutilizado del demo greatbaby con integración probada.
 * Documentación: https://developers.siigo.com/
 *
 * - Auth con OAuth simple (POST /auth con username + access_key → access_token 24h)
 * - Base URL: https://api.siigo.com/v1/...
 * - Reintento automático al 401 (token expirado) y al 429 (rate limit con backoff)
 * - Sanitización de logs (redacta tokens y access_keys)
 * - Header Partner-Id obligatorio si viene configurado
 */
class SiigoClient
{
    private const BASE_URL = 'https://api.siigo.com';
    private const AUTH_URL = 'https://api.siigo.com/auth';
    private const TIMEOUT_SEGUNDOS = 30;
    private const MAX_REINTENTOS = 3;

    public function __construct(private readonly SiigoConfig $config) {}

    public function authenticate(): string
    {
        if ($this->tokenEsVigente()) {
            return (string) $this->config->token_cache;
        }

        if (empty($this->config->username) || empty($this->config->access_key)) {
            throw new RuntimeException('Credenciales SIIGO no configuradas.');
        }

        $response = Http::timeout(self::TIMEOUT_SEGUNDOS)
            ->acceptJson()->asJson()
            ->post(self::AUTH_URL, [
                'username' => $this->config->username,
                'access_key' => $this->config->access_key,
            ]);

        if ($response->failed()) {
            Log::channel('siigo')->error('SIIGO auth failed', [
                'status' => $response->status(),
                'body' => $this->sanitizarRespuesta($response->body()),
            ]);
            throw new RuntimeException('Fallo autenticando contra SIIGO: HTTP '.$response->status());
        }

        $token = (string) $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        $this->config->forceFill([
            'token_cache' => $token,
            'token_expires_at' => now()->addSeconds(max(60, $expiresIn - 30)),
        ])->save();

        return $token;
    }

    /**
     * Ejecuta un request autenticado con reintento inteligente.
     *
     * @param  array<string, mixed>  $payload
     */
    public function request(string $method, string $path, array $payload = [], int $intento = 1): Response
    {
        $token = $this->authenticate();
        $method = strtoupper($method);
        $url = rtrim(self::BASE_URL, '/').'/'.ltrim($path, '/');

        $pending = $this->cliente($token);

        $response = match ($method) {
            'GET' => $pending->get($url, $payload),
            'POST' => $pending->post($url, $payload),
            'PUT' => $pending->put($url, $payload),
            'PATCH' => $pending->patch($url, $payload),
            'DELETE' => $pending->delete($url, $payload),
            default => throw new RuntimeException("Método HTTP no soportado: {$method}"),
        };

        if ($response->status() === 401 && $intento === 1) {
            $this->forgetToken();
            return $this->request($method, $path, $payload, $intento + 1);
        }

        if ($response->status() === 429 && $intento <= self::MAX_REINTENTOS) {
            $espera = (int) ($response->header('Retry-After') ?: pow(2, $intento));
            Log::channel('siigo')->warning('SIIGO rate limit', [
                'path' => $path, 'intento' => $intento, 'espera_segundos' => $espera,
            ]);
            sleep(min($espera, 30));
            return $this->request($method, $path, $payload, $intento + 1);
        }

        if ($response->failed()) {
            Log::channel('siigo')->error('SIIGO request failed', [
                'method' => $method, 'path' => $path,
                'status' => $response->status(),
                'body' => $this->sanitizarRespuesta($response->body()),
            ]);
        }

        return $response;
    }

    private function cliente(string $token): PendingRequest
    {
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];

        if (! empty($this->config->partner_id)) {
            $headers['Partner-Id'] = (string) $this->config->partner_id;
        }

        return Http::withHeaders($headers)
            ->timeout(self::TIMEOUT_SEGUNDOS)
            ->acceptJson()->asJson();
    }

    private function tokenEsVigente(): bool
    {
        if (empty($this->config->token_cache) || $this->config->token_expires_at === null) {
            return false;
        }
        return $this->config->token_expires_at->isFuture();
    }

    private function forgetToken(): void
    {
        $this->config->forceFill([
            'token_cache' => null, 'token_expires_at' => null,
        ])->save();
    }

    private function sanitizarRespuesta(string $body): string
    {
        $body = preg_replace('/"access_token"\s*:\s*"[^"]*"/', '"access_token":"[REDACTED]"', $body) ?? $body;
        $body = preg_replace('/"access_key"\s*:\s*"[^"]*"/', '"access_key":"[REDACTED]"', $body) ?? $body;
        return mb_substr($body, 0, 1500);
    }

    /** @return array{ok: bool, mensaje: string} */
    public function probarConexion(): array
    {
        try {
            $this->forgetToken();
            $this->authenticate();
            return ['ok' => true, 'mensaje' => 'Conexión exitosa con SIIGO ('.$this->config->ambiente.').'];
        } catch (RuntimeException $e) {
            Log::channel('siigo')->warning('SIIGO probarConexion fallo controlado', ['mensaje' => $e->getMessage()]);
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        } catch (RequestException|\Throwable $e) {
            Log::channel('siigo')->error('SIIGO probarConexion excepción', [
                'mensaje' => $e->getMessage(), 'clase' => $e::class,
            ]);
            return ['ok' => false, 'mensaje' => 'Error inesperado al contactar SIIGO. Revisa los logs.'];
        }
    }
}
