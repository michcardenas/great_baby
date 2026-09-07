<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionErp extends Model
{
    protected $table = 'notificaciones_erp';

    protected $guarded = ['id'];

    protected $casts = [
        'leida_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crea una notificación (para un usuario o broadcast si user_id=null).
     *
     * @param array{tipo:string, titulo:string, mensaje?:string, url?:string, icono?:string, color?:string, user_id?:?int} $data
     */
    public static function crear(array $data): self
    {
        return static::create(array_merge([
            'color' => 'gray',
            'icono' => 'heroicon-o-bell',
        ], $data));
    }

    public static function noLeidasPara(int $userId): int
    {
        return static::where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })->whereNull('leida_at')->count();
    }
}
