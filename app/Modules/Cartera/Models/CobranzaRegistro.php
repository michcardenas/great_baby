<?php

namespace App\Modules\Cartera\Models;

use App\Models\Contacto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobranzaRegistro extends Model
{
    protected $table = 'cobranzas_registro';

    protected $fillable = [
        'factura_id', 'contacto_id', 'canal', 'tramo',
        'estado', 'mensaje', 'respuesta_api', 'gestor_id', 'enviado_at',
    ];

    protected $casts = [
        'respuesta_api' => 'array',
        'enviado_at' => 'datetime',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(FacturaVenta::class, 'factura_id');
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function gestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestor_id');
    }
}
