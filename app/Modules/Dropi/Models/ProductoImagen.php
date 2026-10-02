<?php

namespace App\Modules\Dropi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * FASE H · Imagen asociada a un producto · hasta 5 slots por producto.
 *
 * Guardadas en storage/app/public/productos/{producto_id}/{filename}.
 * `path` es relativo al disco "public". `url()` resuelve el storage/
 * para servir al front.
 */
class ProductoImagen extends Model
{
    protected $table = 'producto_imagenes';

    protected $fillable = [
        'producto_id', 'path', 'nombre_original', 'tamano_bytes', 'mime', 'orden',
    ];

    protected $casts = [
        'tamano_bytes' => 'integer',
        'orden' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** URL pública que el front puede renderizar en <img src>. */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * FASE F4.M4 · al eliminar la fila, borrar el archivo físico del disco
     * para evitar fuga de almacenamiento (antes quedaban huérfanos en
     * storage/app/public/productos/{id}/ al eliminar la imagen).
     */
    protected static function booted(): void
    {
        static::deleted(function (ProductoImagen $img): void {
            if ($img->path && Storage::disk('public')->exists($img->path)) {
                Storage::disk('public')->delete($img->path);
            }
        });
    }
}
