<?php

namespace App\Console\Commands;

use App\Models\Contacto;
use App\Models\User;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\PagoVenta;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Inventario\Enums\EstadoTomaFisica;
use App\Modules\Inventario\Enums\EstadoTraslado;
use App\Modules\Inventario\Models\TomaFisica;
use App\Modules\Inventario\Models\TomaFisicaItem;
use App\Modules\Inventario\Models\Traslado;
use App\Modules\Inventario\Models\TrasladoItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Fix demo B1 · Seeder orquestador idempotente para tener el sistema
 *   con datos consistentes en TODOS los módulos para la demo:
 *
 *   - Users con demo1234
 *   - 3 traslados (Borrador, EnTránsito, Recibido) con items
 *   - 2 tomas físicas (En Conteo + Ajustada)
 *   - Kardex saludable en 3 ubicaciones
 *   - Pagos extra en facturas existentes
 *   - Contactos B2B para portal
 *
 *   Puede correrse muchas veces sin duplicar (idempotente por número/email).
 */
class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset {--force : Ejecutar sin confirmación}';

    protected $description = 'Deja el sistema con datos consistentes para demo. Idempotente.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('¿Sembrar / actualizar datos de demo? (Idempotente, no borra nada crítico)')) {
            return self::SUCCESS;
        }

        $this->info('=== FASE 1: Users con demo1234 ===');
        $this->sembrarUsers();

        $this->info('=== FASE 2: Kardex saludable ===');
        $this->sembrarKardex();

        $this->info('=== FASE 3: Traslados demo ===');
        $this->sembrarTraslados();

        $this->info('=== FASE 4: Tomas físicas ===');
        $this->sembrarTomasFisicas();

        $this->info('=== FASE 5: Pagos extra en facturas ===');
        $this->sembrarPagos();

        $this->info('=== FASE 6: Cliente Portal B2B ===');
        $this->sembrarClienteB2B();

        $this->info(PHP_EOL . '✅ Demo lista. Credenciales: demo1234');
        $this->tabla();

        return self::SUCCESS;
    }

    protected function sembrarUsers(): void
    {
        $pwd = Hash::make('demo1234');
        $perfiles = [
            ['aracely@greatbaby.com', 'Aracely Sánchez (dueña)', 'Aracely'],
            ['gerente@greatbaby.com', 'Gerente General', 'Gerente'],
            ['contador@greatbaby.com', 'Contador Público', 'Contador'],
            ['vendedor@greatbaby.com', 'Vendedor Comercial', 'Vendedor'],
            ['alistador@greatbaby.com', 'Alistador Bodega', 'Alistador'],
            ['sac@greatbaby.com', 'Servicio al Cliente', 'ServicioCliente'],
            ['gerencia@greatbaby.com', 'Gerencia General', 'Gerencia'],
        ];
        foreach ($perfiles as [$email, $nombre, $rol]) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
            $u = User::firstOrCreate(['email' => $email], ['name' => $nombre, 'password' => $pwd]);
            $u->password = $pwd;
            $u->save();
            if (! $u->hasRole($rol)) $u->assignRole($rol);
        }
        $this->line('  · 7 users listos con demo1234');
    }

    protected function sembrarKardex(): void
    {
        // Fix demo · sembrar en LAS DOS PRIMERAS ubicaciones activas para que
        //   los traslados demo tengan origen con stock (antes fallaba porque
        //   solo se sembraba en la primera Venta y los traslados nacían en
        //   ubicaciones de bodega sin stock).
        $ubics = InventarioUbicacion::where('activa', true)->orderBy('id')->take(2)->get();
        if ($ubics->isEmpty()) { $this->warn('  ⚠ sin ubicaciones activas'); return; }

        $agregados = 0;
        foreach (ProductoVariante::inRandomOrder()->take(20)->get() as $v) {
            foreach ($ubics as $ubic) {
                $saldo = InventarioMovimiento::where('variante_id', $v->id)
                    ->where('ubicacion_id', $ubic->id)->sum('cantidad');
                if ($saldo < 15) {
                    InventarioMovimiento::create([
                        'variante_id' => $v->id,
                        'ubicacion_id' => $ubic->id,
                        'tipo' => 'entrada_compra',
                        'cantidad' => 50 - (int) $saldo,
                        'costo_unit' => rand(5000, 25000),
                        'referencia_tipo' => 'demo_seed',
                        'referencia_id' => 0,
                        'user_id' => User::first()?->id ?? 1,
                        'notas' => 'demo:reset · stock inicial',
                        'created_at' => now()->subDays(rand(1, 20)),
                    ]);
                    $agregados++;
                }
            }
        }
        $this->line("  · Kardex: {$agregados} movimientos de stock inicial en 2 ubicaciones");
    }

    protected function sembrarTraslados(): void
    {
        $ubics = InventarioUbicacion::where('activa', true)->orderBy('id')->take(2)->get();
        if ($ubics->count() < 2) { $this->warn('  ⚠ faltan ubicaciones'); return; }
        [$o, $d] = [$ubics[0], $ubics[1]];

        // Fix demo · borrar traslados demo previos (sin items ni movs kardex
        // porque están en Borrador) para regenerarlos con stock garantizado en
        // el origen específico.
        Traslado::where('numero', 'like', 'TRA-DEMO-%')->get()->each(function ($t) {
            TrasladoItem::where('traslado_id', $t->id)->delete();
            $t->forceDelete();
        });

        $vars = ProductoVariante::inRandomOrder()->limit(6)->get();
        $creados = 0;
        for ($i = 1; $i <= 3; $i++) {
            $t = Traslado::create([
                'numero' => 'TRA-DEMO-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'origen_id' => $o->id, 'destino_id' => $d->id,
                'fecha_solicitud' => now()->subDays($i)->toDateString(),
                'estado' => EstadoTraslado::Borrador,
                'motivo' => 'reposicion',
                'observaciones' => 'Traslado de demo para presentación',
            ]);
            foreach ($vars->slice(($i - 1) * 2, 2) as $v) {
                $qty = rand(3, 8);
                // GARANTIZAR STOCK en el ORIGEN de este traslado.
                $saldo = InventarioMovimiento::where('variante_id', $v->id)
                    ->where('ubicacion_id', $o->id)->sum('cantidad');
                if ((int) $saldo < $qty + 5) {
                    InventarioMovimiento::create([
                        'variante_id' => $v->id,
                        'ubicacion_id' => $o->id,
                        'tipo' => 'entrada_compra',
                        'cantidad' => 50,
                        'costo_unit' => rand(5000, 25000),
                        'referencia_tipo' => 'demo_seed_traslado',
                        'referencia_id' => $t->id,
                        'user_id' => User::first()?->id ?? 1,
                        'notas' => 'demo:reset · stock garantizado para traslado '.$t->numero,
                        'created_at' => now()->subDays(rand(2, 20)),
                    ]);
                }
                TrasladoItem::create(['traslado_id' => $t->id, 'variante_id' => $v->id, 'cantidad_solicitada' => $qty]);
            }
            $creados++;
        }
        $this->line("  · {$creados} traslados demo creados con stock garantizado en origen");
    }

    protected function sembrarTomasFisicas(): void
    {
        $ubic = InventarioUbicacion::where('activa', true)->first();
        if (! $ubic) return;

        $existentes = TomaFisica::where('numero', 'like', 'TF-DEMO-%')->count();
        if ($existentes >= 2) { $this->line("  · Ya existen {$existentes} tomas demo"); return; }

        $userId = User::whereHas('roles', fn ($q) => $q->where('name', 'Aracely'))->first()?->id ?? 1;

        for ($i = 1; $i <= 2; $i++) {
            $tf = TomaFisica::create([
                'numero' => 'TF-DEMO-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'ubicacion_id' => $ubic->id,
                'creada_por' => $userId,
                'fecha_conteo' => now()->subDays($i)->toDateString(),
                'estado' => EstadoTomaFisica::Borrador,
                'tipo' => 'ciclico',
            ]);
            foreach (ProductoVariante::inRandomOrder()->limit(5)->get() as $v) {
                $it = TomaFisicaItem::firstOrNew(['toma_id' => $tf->id, 'variante_id' => $v->id]);
                $it->saldo_sistema = rand(10, 30);
                $it->costo_unit = rand(5000, 20000);
                $it->save();
            }
        }
        $this->line('  · 2 tomas físicas demo creadas con items snapshot');
    }

    protected function sembrarPagos(): void
    {
        $facturas = FacturaVenta::whereDoesntHave('pagos')->take(6)->get();
        $creados = 0;
        foreach ($facturas as $f) {
            $existentes = PagoVenta::where('factura_id', $f->id)->count();
            if ($existentes > 0) continue;
            try {
                PagoVenta::create([
                    'factura_id' => $f->id,
                    'contacto_id' => $f->contacto_id,
                    'fecha_pago' => now()->subDays(rand(1, 30))->toDateString(),
                    'monto' => round((float) $f->total * 0.5, 2),
                    'medio_pago' => ['transferencia','efectivo','tarjeta'][rand(0,2)],
                    'referencia' => 'DEMO-'.strtoupper(uniqid()),
                    'notas' => 'Pago parcial demo',
                    'registrado_por' => 1,
                ]);
                $creados++;
            } catch (\Throwable $e) {}
        }
        $this->line("  · {$creados} pagos parciales creados");
    }

    protected function sembrarClienteB2B(): void
    {
        $c = Contacto::where('email_portal_key', 'cliente.demo@greatbaby.com')->first();
        if (! $c) {
            $c = Contacto::first();
            if (! $c) return;
        }
        $c->password = Hash::make('demo1234');
        $c->portal_habilitado = 1;
        $c->save();
        $this->line("  · Cliente B2B '{$c->email_portal_key}' con demo1234");
    }

    protected function tabla(): void
    {
        $this->newLine();
        $this->table(
            ['Perfil', 'Email', 'Contraseña'],
            [
                ['Aracely (dueña)', 'aracely@greatbaby.com', 'demo1234'],
                ['Gerente', 'gerente@greatbaby.com', 'demo1234'],
                ['Contador', 'contador@greatbaby.com', 'demo1234'],
                ['Vendedor', 'vendedor@greatbaby.com', 'demo1234'],
                ['Alistador', 'alistador@greatbaby.com', 'demo1234'],
                ['SAC', 'sac@greatbaby.com', 'demo1234'],
                ['Gerencia', 'gerencia@greatbaby.com', 'demo1234'],
                ['Portal B2B', 'cliente.demo@greatbaby.com', 'demo1234'],
            ]
        );
    }
}
