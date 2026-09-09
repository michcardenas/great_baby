<?php

namespace App\Modules\Inventario\Models;

use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Re-audit M3 PATRÓN κ · $guarded reemplaza $fillable (consistencia η).
 */
class AlertaStockConfig extends Model
{
    protected $table = 'alertas_stock_config';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'stock_minimo' => 'integer',
        'stock_maximo' => 'integer',
        'punto_reorden' => 'integer',
        'cantidad_reorden' => 'integer',
        'notificar_email' => 'boolean',
        'notificar_whatsapp' => 'boolean',
        'activa' => 'boolean',
    ];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class);
    }

    public function disparadas(): HasMany
    {
        return $this->hasMany(AlertaStockDisparada::class, 'config_id');
    }
}
