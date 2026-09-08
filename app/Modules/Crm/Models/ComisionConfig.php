<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionConfig extends Model
{
    protected $table = 'comisiones_config';

    protected $fillable = [
        'vendedor_id', 'porcentaje_base', 'cobra_solo_cobrado',
        'meta_mensual', 'bono_por_meta_pct', 'activo', 'notas',
    ];

    protected $casts = [
        'porcentaje_base' => 'decimal:2',
        'cobra_solo_cobrado' => 'boolean',
        'meta_mensual' => 'decimal:2',
        'bono_por_meta_pct' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }
}
