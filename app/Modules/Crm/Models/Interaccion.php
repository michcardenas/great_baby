<?php

namespace App\Modules\Crm\Models;

use App\Models\Contacto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interaccion extends Model
{
    protected $table = 'crm_interacciones';

    protected $fillable = [
        'contacto_id', 'user_id', 'tipo', 'asunto', 'detalle',
        'resultado', 'ocurrida_at', 'proxima_accion_at', 'proxima_accion_nota', 'adjuntos',
    ];

    protected $casts = [
        'ocurrida_at' => 'datetime',
        'proxima_accion_at' => 'datetime',
        'adjuntos' => 'array',
    ];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function tipos(): array
    {
        return [
            'llamada' => '📞 Llamada',
            'correo' => '✉️ Correo',
            'whatsapp' => '📱 WhatsApp',
            'visita' => '🚶 Visita',
            'reunion' => '👥 Reunión',
            'nota' => '📝 Nota interna',
            'reclamo' => '⚠️ Reclamo',
        ];
    }

    public static function resultados(): array
    {
        return [
            'exitoso' => '✅ Exitoso',
            'sin_respuesta' => '📭 Sin respuesta',
            'reagendar' => '🔁 Reagendar',
            'cerrado' => '🔒 Cerrado',
        ];
    }
}
