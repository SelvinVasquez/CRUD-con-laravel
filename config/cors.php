<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rutas con CORS
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
    | Orígenes permitidos, separados por coma en CORS_ALLOWED_ORIGINS.
    | La app móvil no usa CORS; solo la interfaz web. En producción debe ser
    | el dominio de la web, por ejemplo: https://cineteca.midominio.com
    */

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', '*'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
