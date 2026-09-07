<?php

namespace App\Modules\Dropi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DropiEstadoBitacora extends Model
{
    protected $table = 'dropi_estados_bitacora';

    public $timestamps = false;

    protected $fillable = [
        'pedido_id', 'estado_desde', 'estado_hasta',
        'fuente', 'payload', 'user_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
