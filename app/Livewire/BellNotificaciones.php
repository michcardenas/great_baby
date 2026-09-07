<?php

namespace App\Livewire;

use App\Models\NotificacionErp;
use Livewire\Attributes\On;
use Livewire\Component;

class BellNotificaciones extends Component
{
    public bool $abierto = false;

    public function render()
    {
        $userId = auth()->id();
        $lista = NotificacionErp::query()
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('livewire.bell-notificaciones', [
            'notificaciones' => $lista,
            'noLeidas' => $lista->whereNull('leida_at')->count(),
        ]);
    }

    #[On('nueva-notificacion')]
    public function refrescar(): void
    {
        // Livewire re-render automático
    }

    public function toggle(): void
    {
        $this->abierto = ! $this->abierto;
    }

    public function marcarTodasLeidas(): void
    {
        NotificacionErp::query()
            ->where(function ($q) {
                $q->whereNull('user_id')->orWhere('user_id', auth()->id());
            })
            ->whereNull('leida_at')
            ->update(['leida_at' => now()]);
    }

    public function marcarLeida(int $id): void
    {
        // Scope obligatorio por usuario (o broadcast) para evitar IDOR:
        // sin esto cualquier user marca leídas de otro.
        $userId = auth()->id();
        NotificacionErp::where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->update(['leida_at' => now()]);
    }
}
