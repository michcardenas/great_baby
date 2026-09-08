<?php

namespace App\Modules\Dropi\Models;

use App\Modules\Catalogo\Models\Categoria;
use App\Modules\Catalogo\Models\Coleccion;
use App\Modules\Catalogo\Models\Impuesto;
use App\Modules\Catalogo\Models\Marca;
use App\Modules\Catalogo\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Producto extends Model implements AuditableContract, HasMedia
{
    use Auditable, InteractsWithMedia, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'referencia', 'nombre', 'categoria', 'descripcion',
        'precio_proveedor', 'activo', 'requiere_talla', 'es_set',
        'marca_id', 'categoria_id', 'coleccion_id', 'unidad_medida_id', 'impuesto_id',
        'neto', 'peso_gr', 'alto_cm', 'ancho_cm', 'largo_cm',
        // REU-1 · Parametrización contable
        'cta_ingreso', 'cta_iva_venta', 'cta_costo', 'cta_inventario',
        'cta_devolucion', 'cta_descuento', 'centro_costo', 'notas_contables',
        // M8 · Marketing rich
        'copy_comercial', 'specs_json', 'keywords_seo', 'beneficios',
    ];

    protected $casts = [
        'precio_proveedor' => 'decimal:2',
        'activo' => 'boolean',
        'requiere_talla' => 'boolean',
        'es_set' => 'boolean',
        'neto' => 'boolean',
        'peso_gr' => 'decimal:2',
        'alto_cm' => 'decimal:2',
        'ancho_cm' => 'decimal:2',
        'largo_cm' => 'decimal:2',
    ];

    public function variantes(): HasMany { return $this->hasMany(ProductoVariante::class); }
    public function marca(): BelongsTo { return $this->belongsTo(Marca::class); }
    public function categoriaMaestra(): BelongsTo { return $this->belongsTo(Categoria::class, 'categoria_id'); }
    public function coleccion(): BelongsTo { return $this->belongsTo(Coleccion::class); }
    public function unidadMedida(): BelongsTo { return $this->belongsTo(UnidadMedida::class); }
    public function impuesto(): BelongsTo { return $this->belongsTo(Impuesto::class); }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('imagenes')->useDisk('public');
    }

    /**
     * Cuenta contable efectiva: la del producto si tiene, sino el default global.
     * REU-1: para asientos, PDFs contables y exportación a SIIGO.
     */
    public function cta(string $tipo): ?string
    {
        $col = 'cta_' . $tipo;
        $valor = $this->{$col} ?? null;
        // Explícito: null/'' = usar default. "0" o "0000" son válidos.
        if ($valor !== null && $valor !== '') return (string) $valor;
        $default = setting('contable.cta_' . $tipo . '_default');
        return ($default !== null && $default !== '') ? (string) $default : null;
    }

    public function centroCosto(): ?string
    {
        if ($this->centro_costo !== null && $this->centro_costo !== '') return (string) $this->centro_costo;
        $default = setting('contable.centro_costo_default');
        return ($default !== null && $default !== '') ? (string) $default : null;
    }
}
