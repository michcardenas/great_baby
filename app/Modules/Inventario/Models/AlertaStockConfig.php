<?php

namespace App\Modules\Inventario\Models;

use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertaStockConfig extends Model
{
    protected $table = 'alertas_stock_config';

    protected $fillable = [
        'variante_id', 'ubicacion_id',
        'stock_minimo', 'stock_maximo', 'punto_reorden', 'cantidad_reorden',
        'notificar_email', 'notificar_whatsapp', 'activa',
    ];

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
