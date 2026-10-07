<?php

namespace App\Modules\Siigo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SiigoConfig extends Model
{
    protected $table = 'siigo_config';

    protected $fillable = [
        'username', 'access_key', 'partner_id',
        'ambiente', 'activo', 'push_auto',
        'nit_emisor', 'tipo_documento_id', 'seller_id', 'payment_type_id',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'sync_catalogos_at' => 'datetime',
        'sync_productos_at' => 'datetime',
        'sync_clientes_at' => 'datetime',
        'sync_stock_at' => 'datetime',
        'push_auto_updated_at' => 'datetime',
        // Sin el cast a datetime, el `diffForHumans()` de la tarjeta de estado
        // revienta la pantalla entera (ya nos pasó con recepciones).
        // `ultimo_auth_ok` se deja en null mientras nadie haya probado nunca:
        // la vista distingue true / false / «sin probar».
        'ultimo_auth_at' => 'datetime',
        'ultimo_auth_ok' => 'bool',
        'activo' => 'bool',
        'push_auto' => 'bool',
        'tipo_documento_id' => 'integer',
        'seller_id' => 'integer',
        'payment_type_id' => 'integer',
    ];

    // Access key SIEMPRE encriptado en BD, transparente al leer/escribir
    public function setAccessKeyAttribute(?string $value): void
    {
        $this->attributes['access_key'] = ($value === null || $value === '')
            ? null : Crypt::encryptString($value);
    }

    public function getAccessKeyAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function current(): self
    {
        $config = self::query()->first();
        if ($config === null) {
            $config = self::create(['ambiente' => 'sandbox', 'activo' => false]);
        }
        return $config;
    }

    /**
     * F8 · kill-switch efectivo del push automático.
     * Precedencia: valor persistido en BD (`push_auto` no NULL) > env `FEATURE_SIIGO_PUSH_AUTO`.
     * Cache 60s para no golpear la BD en cada job encolado.
     */
    public static function pushAutoActivo(): bool
    {
        return Cache::remember('siigo:push_auto', 60, function () {
            try {
                $db = self::query()->value('push_auto');
                if ($db !== null) return (bool) $db;
            } catch (\Throwable) {
                // BD no disponible (tests unit sin migraciones) → cae al env.
            }
            return (bool) config('siigo.push_auto', false);
        });
    }

    /** F8 · invalidar el cache tras un toggle desde UI (Vue o Filament). */
    public static function invalidarPushAutoCache(): void
    {
        Cache::forget('siigo:push_auto');
    }
}
