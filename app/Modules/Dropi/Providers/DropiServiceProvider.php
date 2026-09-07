<?php

namespace App\Modules\Dropi\Providers;

use App\Modules\Dropi\Clients\DropiApiClient;
use App\Modules\Dropi\Clients\DropiClientInterface;
use App\Modules\Dropi\Clients\DropiMockClient;
use App\Modules\Facturacion\Emisores\AriEmisor;
use App\Support\Contracts\EmisorDocumentoFiscal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class DropiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Emisor fiscal (ARI por defecto; swap fácil a SIIGO/Facture cuando toque).
        $this->app->singleton(EmisorDocumentoFiscal::class, function () {
            return new AriEmisor(
                env('ARI_API_URL', ''),
                env('ARI_API_KEY', ''),
            );
        });

        $this->app->singleton(DropiClientInterface::class, function ($app) {
            $config = $app['config']->get('dropi');

            return match ($config['driver']) {
                'api' => new DropiApiClient(
                    Http::baseUrl($config['api']['url'])
                        ->withToken($config['api']['key'])
                        ->timeout($config['api']['timeout'])
                        ->acceptJson(),
                    $config['api']['url'],
                    $config['api']['key'],
                ),
                default => new DropiMockClient($config['mock']['fixtures_path']),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
