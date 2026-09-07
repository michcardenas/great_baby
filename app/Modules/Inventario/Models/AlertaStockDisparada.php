<?php

namespace App\Modules\Inventario\Models;

use App\Models\User;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaStockDisparada extends Model
{
    protected $table = 'alertas_stock_disparadas';

    protected $fillable = [
        'config_id', 'variante_id', 'ubicacion_id',
        'tipo', 'saldo_al_disparar',
        'resuelta', 'resuelta_at', 'resuelta_por',
    ];

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
