<?php

namespace App\Modules\Dropi\Models;

use App\Modules\Dropi\Enums\TipoMovimientoWallet;
use App\Modules\Dropi\Listeners\RegistrarAsientoWalletDropi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiWalletMovimiento extends Model implements AuditableContract
{
    use Auditable;

    protected static function booted(): void
    {
        // P4 · Cada wallet movement contabiliza automáticamente.
        // Idempotente en el listener (borra asientos previos por (origen, id)).
        static::created(function (self $m) {
            (new RegistrarAsientoWalletDropi())->handle($m);
        });
        static::updated(function (self $m) {
            if ($m->wasChanged(['monto', 'tipo', 'fecha', 'pedido_id'])) {
                (new RegistrarAsientoWalletDropi())->handle($m);
            }
        });
    }

    protected $table = 'dropi_wallet_movimientos';

    protected $fillable = [
        'dropi_movimiento_id',
        'fecha', 'tipo', 'monto', 'pedido_id', 'categoria', 'fuente',
    ];

    protected $casts = [
        'fecha' => 'date',
        'tipo' => TipoMovimientoWallet::class,
        'monto' => 'decimal:2',
        'fuente' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_id');
    }
}
