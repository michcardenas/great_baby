<?php

namespace App\Modules\Inventario\Models;

use App\Models\User;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Inventario\Enums\EstadoTraslado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Traslado extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'traslados_inventario';

    // Fix auditor #20: 'estado', 'ejecutado_por', 'fecha_ejecucion' sólo se setean desde EjecutarTraslado.
    protected $fillable = [
        'numero', 'origen_id', 'destino_id',
        'solicitado_por', 'fecha_solicitud',
        'motivo', 'observaciones',
    ];

    protected static array $backendOnly = ['estado', 'ejecutado_por', 'fecha_ejecucion'];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_ejecucion' => 'date',
        'estado' => EstadoTraslado::class,
    ];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'destino_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function ejecutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TrasladoItem::class, 'traslado_id');
    }

    public static function siguienteNumero(): string
    {
        $year = now()->year;
        $ultimo = static::query()
            ->where('numero', 'like', "TRA-{$year}-%")
            ->orderByDesc('id')
            ->value('numero');
        $seq = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return sprintf('TRA-%d-%06d', $year, $seq);
    }
}
