<?php

namespace App\Modules\Dropi\Models;

use App\Modules\Dropi\Enums\TipoMovimientoWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DropiWalletMovimiento extends Model implements AuditableContract
{
    use Auditable;

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
