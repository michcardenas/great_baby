<?php

namespace App\Modules\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $fillable = ['padre_id', 'codigo', 'nombre', 'cuenta_puc_ingreso', 'cuenta_puc_costo', 'activa'];
    protected $casts = ['activa' => 'boolean'];

    public function padre(): BelongsTo { return $this->belongsTo(self::class, 'padre_id'); }
    public function hijos(): HasMany { return $this->hasMany(self::class, 'padre_id'); }

    public function rutaCompleta(): string
    {
        $p = $this;
        $partes = [];
        while ($p) { $partes[] = $p->nombre; $p = $p->padre; }
        return implode(' › ', array_reverse($partes));
    }
}
