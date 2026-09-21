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
        // C-F1 · Desglose dual de stock
        'desglose_stock', 'stock_directo',
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
        // C-F1 · Desglose dual
        'desglose_stock' => 'boolean',
        'stock_directo' => 'decimal:4',
    ];

    public function variantes(): HasMany { return $this->hasMany(ProductoVariante::class); }

    /**
     * C-F1 · Movimientos de kardex del producto (granular O agregado).
     * Después del creating hook en InventarioMovimiento que auto-populate
     * producto_id desde variante_id, esta relación cubre ambos tipos:
     * los movs granulares tienen producto_id + variante_id, los agregados
     * solo producto_id. Usado por el toggle desglose_stock para bloquear
     * el cambio si hay historia (política enforced también en BD por trigger).
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(\App\Modules\Dropi\Models\InventarioMovimiento::class);
    }

    /**
     * C-F3 FIX ALTO auditor · Observer que inserta el kardex inicial cuando se crea
     * un producto agregado con stock_directo > 0 desde Filament. Sin esto, el
     * campo del form guardaba el número pero NO generaba movimiento → los
     * reportes mostraban 0. Es de raíz: cualquier flujo (Filament, API, comando
     * genérico Producto::create) queda cubierto.
     *
     * El importador F7 (CargarInventarioClienteExcel) crea sus propios movs
     * con marker específico → este observer detecta y NO duplica.
     */
    protected static function booted(): void
    {
        static::created(function (Producto $p) {
            if ($p->desglose_stock || (int) $p->stock_directo <= 0) return;

            $bodega = \App\Modules\Dropi\Models\InventarioUbicacion::query()
                ->where(fn ($q) => $q->where('activa', true)->orWhereNull('activa'))
                ->orderBy('id')
                ->first();
            if (! $bodega) return; // sin bodegas no podemos crear el mov

            $notaMarker = "C-F3 · Stock inicial desde form Filament · producto #{$p->id}";
            $existe = \App\Modules\Dropi\Models\InventarioMovimiento::query()
                ->where('producto_id', $p->id)
                ->whereNull('variante_id')
                ->where('notas', $notaMarker)
                ->exists();
            if ($existe) return;

            \App\Modules\Dropi\Models\InventarioMovimiento::create([
                'producto_id' => $p->id,
                'variante_id' => null,
                'ubicacion_id' => $bodega->id,
                'tipo' => 'stock_inicial_form',
                'cantidad' => (int) $p->stock_directo,
                'notas' => $notaMarker,
            ]);
        });
    }

    /**
     * C-F2 · ¿Este producto lleva stock granular (por variante) o agregado (directo)?
     * Wrapper con nombre semántico sobre `desglose_stock` para código más legible.
     */
    public function esGranular(): bool
    {
        return (bool) $this->desglose_stock;
    }

    /** C-F-QA6 · alias semántico para consistencia con items polimórficos */
    public function esAgregado(): bool
    {
        return ! $this->esGranular();
    }

    /**
     * C-F2 · Saldo físico del producto en una ubicación (o en toda la BD si null).
     *
     *   Granular  → SUM(variantes[*].saldoFisico(ubi))
     *   Agregado  → StockService::saldoFisicoProducto($this->id, $ubi)
     *
     * Es el punto de entrada preferido para código nuevo que necesita stock.
     * Evita duplicar la bifurcación granular/agregado en cada caller.
     */
    public function stockEn(?int $ubicacionId = null): int
    {
        $svc = app(\App\Modules\Inventario\Services\StockService::class);

        // Fix COD3 CRÍTICA re-audit · lookup polimórfico SIEMPRE funcional.
        //   Antes la rama agregada requería feature('desglose_dual')=true; si el
        //   flag venía OFF y el producto era agregado, caía al branch granular,
        //   `$this->variantes()->pluck` daba [] y devolvía 0 en silencio → los
        //   134 productos importados por Aracely desaparecían del inventario
        //   visible pese a tener movimientos vivos. El flag debe ser kill-switch
        //   quirúrgico (bloquear NUEVOS agregados en UI), no borrador de datos.
        if (! $this->esGranular()) {
            return $svc->saldoFisicoProducto($this->id, $ubicacionId);
        }

        // C-F-QA6 · Granular: usa saldosMasivos (1 query) en vez de N queries.
        $ids = $this->variantes()->pluck('id')->all();
        if (empty($ids)) return 0;
        $saldos = $svc->saldosMasivos($ids);
        $total = 0;
        foreach ($saldos as $key => $row) {
            if ($ubicacionId !== null) {
                [$vid, $uid] = explode('-', $key);
                if ((int) $uid !== $ubicacionId) continue;
            }
            $total += (int) $row->saldo;
        }
        return $total;
    }
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
