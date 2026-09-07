<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver activo
    |--------------------------------------------------------------------------
    | mock: usa fixtures locales (dev y CI)
    | api : usa la API real de Dropi (requiere url + key)
    */
    'driver' => env('DROPI_DRIVER', 'mock'),

    'api' => [
        'url' => env('DROPI_API_URL', ''),
        'key' => env('DROPI_API_KEY', ''),
        'timeout' => (int) env('DROPI_API_TIMEOUT', 15),
    ],

    'mock' => [
        'fixtures_path' => storage_path('app/dropi-fixtures'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cortes — §5 diseño Dropi
    |--------------------------------------------------------------------------
    | Ventanas horarias para asignar automáticamente el corte a un pedido nuevo.
    | El primer corte cierra a las 14:00; el segundo a las 18:00.
    */
    'cortes' => [
        1 => ['cierra_a' => '14:00'],
        2 => ['cierra_a' => '18:00'],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (notificaciones al cliente al despachar)
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'mock'),
        'token' => env('WHATSAPP_TOKEN', ''),
        'phone_id' => env('WHATSAPP_PHONE_ID', ''),
        'template_despacho' => env('WHATSAPP_TEMPLATE_DESPACHO', 'pedido_despachado_v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alertas — §14, §16, §20
    |--------------------------------------------------------------------------
    */
    'alertas' => [
        'plazo_dropi_horas' => 72,           // §16 Dropi sanciona a 72h sin despachar
        'aviso_previo_horas' => 48,          // §16 aviso a 48h para tener 24h de margen
        'devolucion_no_confirmada_dias' => 7, // §13 alerta si Dropi marcó devolución hace >7 días
        'entrega_pendiente_dias' => 7,        // §14 empieza a tardar
        'entrega_critica_dias' => 10,         // §14 crítica
        'entrega_contactar_dias' => 15,       // §14 contactar transportadora
        'retiro_wallet_normal_dias' => 15,    // §20 ciclo normal
        'retiro_wallet_aviso_dias' => 20,     // §20 aviso suave
    ],

];
