<?php

// Feature flags del ERP.
// Cada flag envuelve una capacidad nueva que necesita rollout controlado.
// Se leen con feature('nombre') → helper definido en app/helpers.php.

return [

    /*
    |--------------------------------------------------------------------------
    | Desglose dual de stock (Producto agregado vs. Granular por variante)
    |--------------------------------------------------------------------------
    | ON  → productos pueden marcarse `desglose_stock=false` y llevar stock
    |       agregado a nivel producto (sin variantes). El motor bifurca.
    | OFF → comportamiento clásico: todo producto tiene stock por variante.
    |
    | Rollout: se enciende SOLO cuando Fase 10 esté deployada y validada.
    | Rollback: apagar aquí + `php artisan config:cache` desactiva la lógica
    | nueva sin tocar la BD (los productos ya migrados siguen con desglose=true).
    */
    'desglose_dual' => env('FEATURE_DESGLOSE_DUAL', false),

];
