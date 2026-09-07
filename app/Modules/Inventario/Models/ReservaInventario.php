<?php

namespace App\Modules\Inventario\Models;

use App\Models\User;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReservaInventario extends Model
{
    protected $table = 'reservas_inventario';

    protected $fillable = [
        'variante_id', 'ubicacion_id', 'cantidad',
        'origen_type', 'origen_id',
        'expira_at', 'activa', 'user_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'expira_at' => 'datetime',
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

    public function origen(): MorphTo
    {
        return $this->morphTo('origen', 'origen_type', 'origen_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
