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
}
