<?php

namespace App\Modules\Dropi\Models;

use App\Modules\Catalogo\Models\Color;
use App\Modules\Catalogo\Models\Diseno;
use App\Modules\Catalogo\Models\PrecioVariante;
use App\Modules\Catalogo\Models\Talla;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoVariante extends Model
{
    protected $table = 'producto_variantes';

    protected $fillable = [
        'producto_id', 'color_codigo', 'color_nombre',
        'diseno_codigo', 'diseno_nombre', 'talla', 'codigo_barras',
        'color_id', 'diseno_id', 'talla_id', 'stock_minimo',
    ];

    protected $casts = [
        'stock_minimo' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductoVariante $v) {
            // Auto-generar código de barras si no viene y hay producto asociado
            if (! $v->codigo_barras && $v->producto_id) {
                $prod = Producto::find($v->producto_id);
                if ($prod) {
                    $v->codigo_barras = self::generarCodigoBarras(
                        $prod->referencia,
                        $v->color_codigo,
                        $v->diseno_codigo,
                        $v->talla,
                    );
                }
            }
        });
    }

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function color(): BelongsTo { return $this->belongsTo(Color::class); }
    public function diseno(): BelongsTo { return $this->belongsTo(Diseno::class); }
    public function tallaMaestra(): BelongsTo { return $this->belongsTo(Talla::class, 'talla_id'); }
    public function precios(): HasMany { return $this->hasMany(PrecioVariante::class, 'variante_id'); }

    /**
     * §9 Diseño Dropi — Generar código de barras propio de la variante.
     * [Referencia] - [ColorCodigo][DiseñoCodigo] - [Talla]
     */
    public static function generarCodigoBarras(
        string $referencia,
        ?string $colorCodigo,
        ?string $disenoCodigo,
        ?string $talla,
    ): string {
        $tag = trim(($colorCodigo ?? '') . ($disenoCodigo ?? ''));
        $parts = array_filter([$referencia, $tag !== '' ? $tag : null, $talla]);

        return implode('-', $parts);
    }

    public function nombreCompleto(): string
    {
        return trim(($this->producto?->nombre ?? '') . ' · '
            . ($this->color_nombre ?? '') . ' '
            . ($this->diseno_nombre ?? '') . ' '
            . ($this->talla ?? ''));
    }
}
