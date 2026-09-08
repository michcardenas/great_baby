<?php

namespace App\Modules\Gerencia\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GastoOperativo extends Model
{
    use SoftDeletes;
    protected $table = 'gastos_operativos';
    protected $fillable = [
        'numero', 'fecha', 'categoria', 'descripcion', 'monto',
        'proveedor', 'factura_ref', 'metodo_pago', 'tipo',
        'solicita_id', 'aprueba_id', 'estado', 'notas', 'adjuntos',
    ];
    protected $casts = ['fecha' => 'date', 'monto' => 'decimal:2', 'adjuntos' => 'array'];
    public function solicita(): BelongsTo { return $this->belongsTo(User::class, 'solicita_id'); }
    public function aprueba(): BelongsTo { return $this->belongsTo(User::class, 'aprueba_id'); }
}
