<?php

namespace App\Modules\Plantillas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PlantillaDocumento extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'plantillas_documento';

    protected $fillable = [
        'nombre', 'tipo', 'predeterminada', 'activa', 'config',
    ];

    protected $casts = [
        'predeterminada' => 'boolean',
        'activa' => 'boolean',
        'config' => 'array',
    ];

    /** Config default aplicada como fallback de campos que falten en la BD. */
    public static function defaults(): array
    {
        return [
            // Fix demo · `#111` (3 dígitos) no pasaba el validator regex de
            //   PlantillasController que exige `^#[0-9a-fA-F]{6}$`. Al guardar
            //   una plantilla nueva o defaults se disparaba "validation.regex"
            //   raw. Colores siempre en formato hex de 6 dígitos.
            'colores' => ['primario' => '#b45309', 'secundario' => '#78350f', 'texto' => '#111111', 'acento' => '#fef3c7'],
            'tipografia' => 'sans',
            'layout' => 'espacioso',
            'logo_url' => null,
            'logo_alto_px' => 60,
            'encabezado_extra' => '',
            'encabezado_alineacion' => 'izquierda',
            'mostrar_qr' => true,
            'mostrar_bloque_banco' => true,
            'mostrar_bloque_retenciones' => true,
            'mostrar_bloque_notas' => true,
            'mostrar_totales_en_letras' => false,
            'pie_html' => '',
            'terminos_condiciones' => '',
            'sello_texto' => '',
            'watermark_activo' => false,
        ];
    }

    /** Config efectiva (merge default + BD). */
    public function configEfectiva(): array
    {
        return array_replace_recursive(self::defaults(), (array) $this->config);
    }

    /** La plantilla predeterminada por tipo. Cacheada. */
    public static function paraTipo(string $tipo): ?self
    {
        return Cache::remember("plantilla.pred.{$tipo}", 300, function () use ($tipo) {
            return self::where('tipo', $tipo)
                ->where('activa', true)
                ->where('predeterminada', true)
                ->first()
                ?? self::where('tipo', $tipo)->where('activa', true)->first();
        });
    }

    protected static function booted(): void
    {
        $forget = function (self $p) { Cache::forget("plantilla.pred.{$p->tipo}"); };
        static::saved($forget);
        static::deleted($forget);
    }
}
