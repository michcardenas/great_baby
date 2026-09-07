<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Canales Reverb — todos privados. Solo Aracely, Alistadores y Gerencia acceden
 * a broadcasts operativos (montos, guías, clientes).
 */

Broadcast::channel('dropi', function (?User $user) {
    return $user && ($user->esAracely() || $user->hasAnyRole(['Alistador', 'Gerente', 'Contador']));
});

Broadcast::channel('dropi.corte.{corteId}', function (?User $user, int $corteId) {
    return $user && ($user->esAracely() || $user->hasAnyRole(['Alistador', 'Gerente', 'Contador']));
});
