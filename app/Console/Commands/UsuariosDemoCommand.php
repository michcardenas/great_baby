<?php

namespace App\Console\Commands;

use Database\Seeders\UsuariosDemoSeeder;
use Illuminate\Console\Command;

/**
 * C-F9 QA · Alias cómodo del UsuariosDemoSeeder.
 *
 * Lo mismo que `php artisan db:seed --class=UsuariosDemoSeeder`, pero más
 * corto y con el flag `--list` que solo lista las credenciales sin tocar BD.
 *
 *   php artisan gb:usuarios-demo              # crea/actualiza los 10 users
 *   php artisan gb:usuarios-demo --list       # solo muestra la tabla
 */
class UsuariosDemoCommand extends Command
{
    protected $signature = 'gb:usuarios-demo
                            {--list : Solo listar las credenciales, no tocar BD}';

    protected $description = 'C-F9 · Crea/actualiza los 10 usuarios demo (uno por rol) · password: demo';

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->mostrarTabla();
            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => 'UsuariosDemoSeeder', '--force' => true]);
        $this->newLine();
        $this->mostrarTabla();
        return self::SUCCESS;
    }

    private function mostrarTabla(): void
    {
        $rows = array_map(
            fn ($u) => [$u[2], $u[0], UsuariosDemoSeeder::PASSWORD_DEMO],
            UsuariosDemoSeeder::USUARIOS
        );
        $this->table(['Rol', 'Email', 'Password'], $rows);
        $this->info('URL login: /app/login');
        $this->warn('Estos usuarios son SOLO para QA — en producción no se crean.');
    }
}
