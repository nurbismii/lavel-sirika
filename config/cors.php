<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | SIRIKA is currently a session-based web application. Production CORS is
    | intentionally conservative and can be opened with env values when a real
    | API consumer is introduced.
    |
    */

    'paths' => array_values(array_filter(array_map('trim', explode(',', env('CORS_PATHS', ''))))),

    'allowed_methods' => array_values(array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_METHODS',
        'GET,POST,OPTIONS'
    ))))),

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'https://sirika.vdnisite.com'
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => array_values(array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_HEADERS',
        'Content-Type,X-Requested-With,X-CSRF-TOKEN,Authorization'
    ))))),

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
