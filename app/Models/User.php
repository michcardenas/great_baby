<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['Aracely', 'Alistador', 'ServicioCliente', 'Gerencia']);
    }

    public function esAracely(): bool
    {
        return $this->hasAnyRole(['Aracely', 'Gerencia']);
    }

    /**
     * Re-audit M5 SEG-C2 · helper unificado para acceso a Contabilidad.
     *
     * Antes: los controllers pedían `hasAnyRole(['Contador','Gerente'])` pero el
     * seeder solo crea `Aracely,Alistador,ServicioCliente,Gerencia` → Contador
     * y Gerente nunca daban true en runtime; los Filament Pages pedían
     * `esAracely()` (Aracely/Gerencia) → matriz de autorización desalineada
     * entre pantallas del mismo módulo. Este helper es LA autoridad.
     */
    public function esContable(): bool
    {
        return $this->hasAnyRole(['Aracely', 'Gerencia', 'Gerente', 'Contador']);
    }

    public function esAlistador(): bool
    {
        return $this->hasRole('Alistador');
    }

    public function esSac(): bool
    {
        return $this->hasRole('ServicioCliente');
    }

    /**
     * Re-audit M3 λ (SEG-C2) · alcance de bodegas para un Alistador.
     *
     * Se lee de `setting('inventario.alistador_bodegas.<user_id>', [])` que
     * Aracely/Gerencia mantienen desde el panel de Reglas de Negocio.
     * Si el user tiene meta directa `bodegas_asignadas` (JSON) también se
     * respeta. Vacío = SIN acceso (fail-closed) para no permitir escalación
     * silenciosa cuando el operador aún no fue configurado.
     *
     * Aracely/Gerencia NO deben pasar por aquí: usar `esAracely()` primero.
     */
    public function bodegasAsignadasIds(): array
    {
        if (! empty($this->getAttribute('bodegas_asignadas'))) {
            $raw = $this->getAttribute('bodegas_asignadas');
            $arr = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
            return array_values(array_map('intval', $arr));
        }
        if (function_exists('setting')) {
            $arr = (array) setting("inventario.alistador_bodegas.{$this->id}", []);
            return array_values(array_map('intval', $arr));
        }
        return [];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
