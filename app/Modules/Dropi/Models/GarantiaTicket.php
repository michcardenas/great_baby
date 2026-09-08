<?php

namespace App\Modules\Dropi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GarantiaTicket extends Model
{
    protected $table = 'garantia_tickets';

    protected $fillable = [
        'numero', 'pedido_original_id', 'variante_id', 'cantidad',
        'cliente_nombre', 'cliente_telefono', 'descripcion_falla',
        'notas_decision', 'fotos_evidencia', 'valor_reposicion', 'pedido_reposicion_id',
        'estado', 'creado_por', 'decision_por',
        'plazo_concepto_at', 'decision_at',
        'ubicacion_reserva_id', 'closed_at',
    ];

    protected $casts = [
        'plazo_concepto_at' => 'datetime',
        'decision_at' => 'datetime',
        'closed_at' => 'datetime',
        'cantidad' => 'integer',
        'fotos_evidencia' => 'array',
        'valor_reposicion' => 'decimal:2',
    ];

    public function pedidoOriginal(): BelongsTo
    {
        return $this->belongsTo(DropiPedido::class, 'pedido_original_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function decidioPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_por');
    }

    public function ubicacionReserva(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_reserva_id');
    }
}
