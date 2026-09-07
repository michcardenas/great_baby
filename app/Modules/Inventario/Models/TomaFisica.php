<?php

namespace App\Modules\Inventario\Models;

use App\Models\User;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Inventario\Enums\EstadoTomaFisica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TomaFisica extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'tomas_fisicas';

    // Fix auditor #20: 'estado', 'cerrada_at', 'cerrada_por', 'items_diferentes', 'valor_ajuste'
    // sólo se setean desde CerrarTomaFisica.
    protected $fillable = [
        'numero', 'ubicacion_id', 'creada_por',
        'fecha_conteo', 'tipo', 'alcance', 'observaciones',
    ];

    protected static array $backendOnly = ['estado', 'cerrada_at', 'cerrada_por', 'items_diferentes', 'valor_ajuste'];

    protected $casts = [
        'fecha_conteo' => 'date',
        'cerrada_at' => 'datetime',
        'estado' => EstadoTomaFisica::class,
        'valor_ajuste' => 'decimal:2',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TomaFisicaItem::class, 'toma_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public static function siguienteNumero(): string
    {
        $year = now()->year;
        $ultimo = static::query()
            ->where('numero', 'like', "TF-{$year}-%")
            ->orderByDesc('id')
            ->value('numero');
        $seq = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return sprintf('TF-%d-%06d', $year, $seq);
    }
}
