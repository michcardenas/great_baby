<?php

namespace App\Modules\Inventario\Models;

use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrasladoItem extends Model
{
    protected $table = 'traslados_inventario_items';

    protected $fillable = [
        'traslado_id', 'variante_id',
        'cantidad_solicitada', 'cantidad_ejecutada', 'notas',
    ];

    protected $casts = [
        'cantidad_solicitada' => 'integer',
        'cantidad_ejecutada' => 'integer',
    ];

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(Traslado::class, 'traslado_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
