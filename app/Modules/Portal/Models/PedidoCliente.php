<?php

namespace App\Modules\Portal\Models;

use App\Models\Contacto;
use App\Models\User;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Catalogo\Models\ListaPrecios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PedidoCliente extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'pedidos_cliente';

    protected $fillable = [
        'numero', 'contacto_id', 'lista_precios_id', 'estado',
        'subtotal', 'iva', 'total', 'notas_cliente', 'notas_internas',
        'enviado_at', 'aprobado_at', 'rechazado_at',
        'facturado_por_id', 'facturado_at', 'factura_id', 'motivo_rechazo',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'enviado_at' => 'datetime',
        'aprobado_at' => 'datetime',
        'rechazado_at' => 'datetime',
        'facturado_at' => 'datetime',
    ];

    public function contacto(): BelongsTo { return $this->belongsTo(Contacto::class); }
    public function lista(): BelongsTo { return $this->belongsTo(ListaPrecios::class, 'lista_precios_id'); }
    public function items(): HasMany { return $this->hasMany(PedidoClienteItem::class, 'pedido_id'); }
    public function facturadoPor(): BelongsTo { return $this->belongsTo(User::class, 'facturado_por_id'); }
    public function factura(): BelongsTo { return $this->belongsTo(FacturaVenta::class, 'factura_id'); }
}
