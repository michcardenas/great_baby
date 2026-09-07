<?php

namespace App\Modules\Dropi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    public $timestamps = false;

    protected $fillable = [
        'variante_id', 'ubicacion_id', 'tipo', 'cantidad',
        'referencia_tipo', 'referencia_id', 'user_id', 'notas',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'created_at' => 'datetime',
    ];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
