<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Siigo Sync · configuración runtime
    |--------------------------------------------------------------------------
    |
    | Estos valores controlan el comportamiento del push automático a Siigo
    | (Observer + Job). Todo desactivable por .env para no romper prod si algo
    | falla — el ERP local sigue funcionando aunque Siigo API caiga.
    */

    // Driver:
    //   'real' → habla con https://api.siigo.com/ (requiere credenciales válidas)
    //   'fake' → simula respuestas OK para demos/pruebas sin credenciales
    'driver' => env('SIIGO_DRIVER', 'real'),

    // Kill-switch global del push automático. Si false, el Observer NO encola.
    // Manual (botón "Sincronizar con Siigo" en Filament) siempre funciona.
    'push_auto' => (bool) env('FEATURE_SIIGO_PUSH_AUTO', false),

    // Cuando `productos.desglose_stock` es NULL (producto viejo sin la columna
    // seteada), asumimos este valor. false = tratar como AGREGADO (1 producto Siigo).
    'desglose_default' => (bool) env('SIIGO_DESGLOSE_DEFAULT', false),

    // Cola donde despachar el Job PushProductoASiigo. Separada para no
    // pelearse con jobs de dominio (empaque, cartera, etc.).
    'queue' => env('SIIGO_QUEUE', 'siigo'),

    // Rate limit permitido por Siigo (por minuto por empresa).
    //   prod: 100/min
    //   pruebas: 10/min
    // Usar env SIIGO_RATE_LIMIT_PER_MIN para bajar en pruebas.
    'rate_limit_per_min' => (int) env('SIIGO_RATE_LIMIT_PER_MIN', 100),

    // Ventana de debounce: si en los últimos N segundos ya se despachó un job
    // para el mismo producto y acción, no se encola de nuevo (evita duplicados
    // por spam de saves consecutivos).
    'debounce_seconds' => (int) env('SIIGO_DEBOUNCE_SECONDS', 30),
];
