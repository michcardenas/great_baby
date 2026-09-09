<?php

namespace App\Modules\Inventario\Models;

use App\Models\User;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Re-audit M3 PATRÓN ο (FUNC-C6, DATOS-C5) · Auditable trait.
 *   Antes: VerificarAlertasStock decía "iteración por instancia para que
 *   dispare Auditable" pero el modelo NO lo tenía. Auto-resolución era muda.
 * Re-audit M3 PATRÓN κ · $guarded (consistencia η).
 */
class AlertaStockDisparada extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'alertas_stock_disparadas';

    protected $guarded = ['id', 'created_at', 'updated_at', 'clave_abierta'];

    protected $casts = [
        'saldo_al_disparar' => 'integer',
        'resuelta' => 'boolean',
        'resuelta_at' => 'datetime',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(AlertaStockConfig::class, 'config_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class);
    }

    public function resolvedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }
}
