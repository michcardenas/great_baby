<?php

namespace App\Modules\Cartera\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MovimientoContable extends Model
{
    protected $table = 'movimientos_contables';

    protected $fillable = [
        'fecha', 'cuenta_puc', 'tercero_type', 'tercero_id',
        'debe', 'haber', 'origen_type', 'origen_id',
        'descripcion', 'centro_costo', 'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'debe' => 'decimal:2',
        'haber' => 'decimal:2',
    ];

    public function origen(): MorphTo
    {
        return $this->morphTo('origen', 'origen_type', 'origen_id');
    }

    public function tercero(): MorphTo
    {
        return $this->morphTo('tercero', 'tercero_type', 'tercero_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
