<?php

namespace App\Modules\Inventario\Models;

use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TomaFisicaItem extends Model
{
    protected $table = 'tomas_fisicas_items';

    protected $fillable = [
        'toma_id', 'variante_id',
        'saldo_sistema', 'cantidad_contada', 'diferencia',
        'costo_unit', 'notas',
    ];

    protected $casts = [
        'saldo_sistema' => 'integer',
        'cantidad_contada' => 'integer',
        'diferencia' => 'integer',
        'costo_unit' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (TomaFisicaItem $item) {
            if ($item->cantidad_contada !== null) {
                $item->diferencia = (int) $item->cantidad_contada - (int) $item->saldo_sistema;
            }
        });
    }

    public function toma(): BelongsTo
    {
        return $this->belongsTo(TomaFisica::class, 'toma_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
