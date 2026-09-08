<?php

namespace App\Modules\Dropi\Models;

use App\Models\User;
use App\Modules\Dropi\Enums\EstadoEmpaque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpaqueRegistro extends Model
{
    protected $table = 'empaques_registro';

    protected $fillable = [
        'pedido_id', 'operario_id', 'inicio_at', 'fin_at',
        'items_totales', 'items_escaneados', 'duracion_segundos',
        'estado', 'notas', 'foto_path', 'foto_at',
        'foto_purga_intentos', 'foto_purga_ultimo_fallo_at',
    ];

    protected $casts = [
        'inicio_at' => 'datetime',
        'fin_at' => 'datetime',
        'foto_at' => 'datetime',
        'items_totales' => 'integer',
        'items_escaneados' => 'integer',
        'duracion_segundos' => 'integer',
        'foto_purga_intentos' => 'integer',
        'foto_purga_ultimo_fallo_at' => 'datetime',
        'estado' => EstadoEmpaque::class,
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_id');
    }

    public function operario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operario_id');
    }
}
