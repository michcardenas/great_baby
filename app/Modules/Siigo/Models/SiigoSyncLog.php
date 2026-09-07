<?php

namespace App\Modules\Siigo\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiigoSyncLog extends Model
{
    protected $table = 'siigo_sync_log';

    protected $fillable = [
        'recurso', 'estado', 'nuevos', 'actualizados', 'errores',
        'duracion_ms', 'mensaje', 'detalle', 'user_id',
    ];

    protected $casts = ['detalle' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
