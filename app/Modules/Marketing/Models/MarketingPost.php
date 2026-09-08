<?php

namespace App\Modules\Marketing\Models;

use App\Models\User;
use App\Modules\Dropi\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingPost extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_posts';

    protected $fillable = [
        'fecha_publicacion', 'hora_publicacion', 'titulo', 'copy',
        'canal', 'tipo', 'estado', 'producto_id', 'creado_por',
        'url_publicacion', 'notas',
    ];

    protected $casts = [
        'fecha_publicacion' => 'date',
    ];

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function creador(): BelongsTo { return $this->belongsTo(User::class, 'creado_por'); }
}
