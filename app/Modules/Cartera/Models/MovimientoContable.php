<?php

namespace App\Modules\Cartera\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use RuntimeException;

class MovimientoContable extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'movimientos_contables';

    /**
     * Re-audit SEG N2 (landmine) · SoftDeletes obligatorio — DIAN exige
     * inmutabilidad del libro diario 5 años. Cualquier delete accidental
     * (Filament resource, cascade, artisan) queda como `deleted_at`, no
     * borra físicamente.
     * Re-audit SEG C2 · $guarded controlado.
     * Re-audit DATOS M15 · Auditable + $auditExclude reduce ruido en `audits`.
     */
    protected $guarded = ['id'];

    protected array $auditExclude = ['descripcion']; // texto largo, no aporta a auditoría

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

    /**
     * Raíz C · Registrador atómico de asientos con validación de partida doble.
     *
     * Re-audit DATOS #9 · valida también integridad por línea:
     *   - debe >= 0 y haber >= 0 (no negativos)
     *   - debe * haber === 0 (mutuamente exclusivos)
     * Cualquier línea que quiebra estas invariantes aborta antes de commit.
     */
    public static function registrarAsientoAtomico(array $lineas): int
    {
        if (empty($lineas)) return 0;

        $totalDebe = 0.0;
        $totalHaber = 0.0;
        foreach ($lineas as $idx => $l) {
            $debe = (float) ($l['debe'] ?? 0);
            $haber = (float) ($l['haber'] ?? 0);

            if ($debe < 0 || $haber < 0) {
                throw new RuntimeException(
                    "Asiento inválido (línea {$idx}): debe/haber negativos no permitidos (debe={$debe}, haber={$haber})"
                );
            }
            if ($debe > 0 && $haber > 0) {
                throw new RuntimeException(
                    "Asiento inválido (línea {$idx}): debe y haber en la misma línea (debe={$debe}, haber={$haber})"
                );
            }

            $totalDebe += $debe;
            $totalHaber += $haber;
        }

        if (abs($totalDebe - $totalHaber) > 0.01) {
            $origen = $lineas[0]['origen_type'] ?? '?';
            $origenId = $lineas[0]['origen_id'] ?? '?';
            throw new RuntimeException(sprintf(
                'Asiento desbalanceado (%s#%s): ΣDebe=%.2f ΣHaber=%.2f · diff=%.4f',
                class_basename((string) $origen), (string) $origenId,
                $totalDebe, $totalHaber, $totalDebe - $totalHaber,
            ));
        }

        return DB::transaction(function () use ($lineas) {
            foreach ($lineas as $l) {
                static::create($l);
            }
            return count($lineas);
        });
    }
}
