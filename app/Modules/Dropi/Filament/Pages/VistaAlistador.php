<?php

namespace App\Modules\Dropi\Filament\Pages;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Events\PedidoDespachado;
use App\Modules\Dropi\Jobs\NotificarClienteDespachoJob;
use App\Modules\Dropi\Models\DropiAlistadorLock;
use App\Modules\Dropi\Models\DropiCorte;
use App\Modules\Dropi\Models\DropiEstadoBitacora;
use App\Modules\Dropi\Models\DropiPedido;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

/**
 * §5, §6, §11 Diseño Dropi — Vista operativa del alistador.
 * Simplificada, distinta a la vista de gestión de Aracely.
 *
 * Dos modos secuenciales dentro del corte:
 *   RECOLECCIÓN — agrupado por producto, para recorrer bodega una vez.
 *   EMPAQUE     — por pedido, con cola compartida + bloqueo al abrir (2-3 personas).
 */
class VistaAlistador extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Vista del Alistador';

    protected static string|\UnitEnum|null $navigationGroup = 'Dropi';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Vista del Alistador';

    protected string $view = 'dropi.pages.vista-alistador';

    public string $modo = 'recoleccion'; // recoleccion | empaque

    public ?int $corteId = null;

    // §24 · Solo Alistador/Aracely acceden. SAC ve pedidos pero no esta vista operativa.
    public static function canAccess(): bool
    {
        $u = auth()->user();
        return $u ? ($u->esAlistador() || $u->esAracely()) : false;
    }

    public function mount(): void
    {
        $corte = DropiCorte::query()->orderByDesc('fecha')->orderByDesc('numero')->first();
        $this->corteId = $corte?->id;
    }

    public function cambiarModo(string $modo): void
    {
        $this->modo = in_array($modo, ['recoleccion', 'empaque'], true) ? $modo : 'recoleccion';
    }

    public function getCorteProperty(): ?DropiCorte
    {
        return $this->corteId ? DropiCorte::find($this->corteId) : null;
    }

    /**
     * §5, §6 modo recolección — totales por producto en el corte.
     */
    public function getRecoleccionProperty(): array
    {
        if (! $this->corteId) {
            return [];
        }

        $pedidos = DropiPedido::query()
            ->where('corte_id', $this->corteId)
            ->whereNotIn('estado', [
                EstadoPedidoDropi::Empacado, EstadoPedidoDropi::Despachado,
                EstadoPedidoDropi::Entregado, EstadoPedidoDropi::Pagado,
                EstadoPedidoDropi::CanceladoDropi, EstadoPedidoDropi::CanceladoGb,
                EstadoPedidoDropi::Devuelto,
            ])
            ->with(['items.variante.producto'])
            ->get();

        $grupos = [];
        foreach ($pedidos as $ped) {
            foreach ($ped->items as $it) {
                $key = $it->sku_dropi;
                $grupos[$key] ??= [
                    'sku' => $key,
                    'producto' => $it->variante?->producto?->nombre ?? 'SKU sin match',
                    'referencia' => $it->variante?->producto?->referencia ?? '—',
                    'variante' => trim(
                        ($it->variante?->color_nombre ?? '') . ' ' . ($it->variante?->diseno_nombre ?? '')
                        . ' ' . ($it->variante?->talla ?? '')
                    ),
                    'total_unidades' => 0,
                    'ubicacion_sugerida' => $it->ubicacionAsignada?->codigo ?? '—',
                ];
                $grupos[$key]['total_unidades'] += (int) $it->cantidad;
            }
        }

        return array_values($grupos);
    }

    /**
     * §6 modo empaque — cola compartida. No incluye los que están bloqueados por otro alistador
     * (excepto por mí, que puedo continuar los que ya tomé).
     */
    public function getEmpaqueProperty(): Collection
    {
        if (! $this->corteId) {
            return DropiPedido::query()->whereRaw('1=0')->get();
        }

        // Locks vencidos (>10 min) los limpia el scheduler `dropi:liberar-locks`.
        // Aquí solo filtramos los activos, sin escribir en cada render.
        $bloqueadosPorOtros = DropiAlistadorLock::query()
            ->where('alistador_id', '!=', auth()->id())
            ->where('heartbeat_at', '>=', now()->subMinutes(10))
            ->pluck('pedido_id');

        return DropiPedido::query()
            ->where('corte_id', $this->corteId)
            ->whereIn('estado', [EstadoPedidoDropi::Pending, EstadoPedidoDropi::Alistando])
            ->whereNotIn('id', $bloqueadosPorOtros)
            ->orderBy('created_at')
            ->limit(20)
            ->get();
    }

    /**
     * §6 — tomar un pedido para empacar. Adquiere el lock; si ya está tomado por otro, avisa.
     */
    public function tomarPedido(int $pedidoId): void
    {
        $userId = auth()->id();

        $lock = DropiAlistadorLock::firstOrCreate(
            ['pedido_id' => $pedidoId],
            ['alistador_id' => $userId],
        );

        if ($lock->alistador_id !== $userId) {
            Notification::make()
                ->title('Pedido ocupado')
                ->body('Otro alistador ya está empacando este pedido.')
                ->warning()->send();

            return;
        }

        $lock->update(['heartbeat_at' => now()]);

        $pedido = DropiPedido::find($pedidoId);
        if ($pedido && $pedido->estado !== EstadoPedidoDropi::Alistando) {
            $anterior = $pedido->estado->value;
            $pedido->update(['estado' => EstadoPedidoDropi::Alistando]);
            DropiEstadoBitacora::create([
                'pedido_id' => $pedidoId,
                'estado_desde' => $anterior,
                'estado_hasta' => EstadoPedidoDropi::Alistando->value,
                'fuente' => 'manual',
                'user_id' => $userId,
            ]);
        }

        Notification::make()->title('Pedido tomado')->success()->send();
    }

    /**
     * §6 — marcar pedido como empacado y liberar el lock.
     * Solo el alistador dueño del lock puede empacar.
     */
    public function empacarPedido(int $pedidoId): void
    {
        $userId = auth()->id();

        $miLock = DropiAlistadorLock::where('pedido_id', $pedidoId)
            ->where('alistador_id', $userId)
            ->exists();

        if (! $miLock) {
            Notification::make()
                ->title('No puedes empacar este pedido')
                ->body('El pedido lo tomó otro alistador o el lock ya expiró — vuelve a tomarlo.')
                ->danger()->send();

            return;
        }

        $pedido = DropiPedido::find($pedidoId);
        if (! $pedido) {
            return;
        }

        $anterior = $pedido->estado->value;
        $pedido->update(['estado' => EstadoPedidoDropi::Empacado]);

        DropiEstadoBitacora::create([
            'pedido_id' => $pedidoId,
            'estado_desde' => $anterior,
            'estado_hasta' => EstadoPedidoDropi::Empacado->value,
            'fuente' => 'manual',
            'user_id' => auth()->id(),
        ]);

        DropiAlistadorLock::where('pedido_id', $pedidoId)->delete();

        Notification::make()->title('Pedido empacado')->success()->send();
    }

    public function despacharPedido(int $pedidoId): void
    {
        $pedido = DropiPedido::find($pedidoId);
        if (! $pedido) {
            return;
        }

        $anterior = $pedido->estado->value;
        $pedido->update([
            'estado' => EstadoPedidoDropi::Despachado,
            'despachado_at' => now(),
        ]);

        DropiEstadoBitacora::create([
            'pedido_id' => $pedidoId,
            'estado_desde' => $anterior,
            'estado_hasta' => EstadoPedidoDropi::Despachado->value,
            'fuente' => 'manual',
            'user_id' => auth()->id(),
        ]);

        // Broadcast tiempo real + Job WhatsApp asincrónico
        event(new PedidoDespachado($pedido));
        NotificarClienteDespachoJob::dispatch($pedido->id);

        Notification::make()
            ->title('✅ Despachado')
            ->body("Guía {$pedido->guia} · Notificación WhatsApp encolada")
            ->success()
            ->send();
    }
}
