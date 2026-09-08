<?php

namespace App\Console\Commands;

use App\Models\Contacto;
use App\Modules\Catalogo\Models\ListaPrecios;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Habilita portal B2B a un contacto existente.
 * Uso: php artisan portal:onboard {documento} [--lista=Mayorista] [--password=]
 */
class OnboardClienteB2B extends Command
{
    protected $signature = 'portal:onboard
        {documento : NIT o cédula del contacto (numero_documento)}
        {--lista=Mayorista : Nombre o ID de la lista de precios a asignar}
        {--password= : Password temporal (si se omite, se genera aleatorio)}';

    protected $description = 'Habilita acceso al Portal B2B para un contacto y le asigna lista de precios.';

    public function handle(): int
    {
        $doc = $this->argument('documento');
        $contacto = Contacto::where('numero_documento', $doc)->first();

        if (! $contacto) {
            $this->error("No existe contacto con documento $doc.");
            return self::FAILURE;
        }

        if (! $contacto->email) {
            $this->error("Contacto no tiene email — no puede loguearse. Actualizá el email primero.");
            return self::FAILURE;
        }

        // Resolver lista de precios
        $listaArg = $this->option('lista');
        $lista = is_numeric($listaArg)
            ? ListaPrecios::find((int) $listaArg)
            : ListaPrecios::where('nombre', 'like', "%{$listaArg}%")->first();

        if (! $lista) {
            $this->error("Lista de precios '$listaArg' no encontrada.");
            return self::FAILURE;
        }

        $pass = $this->option('password') ?: Str::password(10, symbols: false);

        $contacto->forceFill([
            'password' => Hash::make($pass),
            'portal_habilitado' => true,
            'lista_precios_id' => $lista->id,
            'es_cliente_b2b' => true,
            'es_cliente' => true,
            'activo' => true,
        ])->save();

        $this->info('✅ Portal habilitado.');
        $this->newLine();
        $this->table(['Campo', 'Valor'], [
            ['Cliente', $contacto->razon_social ?: $contacto->nombre_completo],
            ['Email', $contacto->email],
            ['Password temporal', $pass],
            ['Lista de precios', $lista->nombre],
            ['URL portal', config('app.url') . '/portal/login'],
        ]);
        $this->newLine();
        $this->line('👉 Enviá estas credenciales por WhatsApp al cliente. Recomienda cambiar contraseña al primer login (funcionalidad pendiente).');

        return self::SUCCESS;
    }
}
