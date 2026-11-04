<?php

/*
|--------------------------------------------------------------------------
| CORS (Kay Care)
|--------------------------------------------------------------------------
|
| Only the explicitly configured SPA origins may call the API with
| credentials. Never use "*" together with supports_credentials.
| FRONTEND_URL accepts a comma separated list of exact origins.
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 600,

    'supports_credentials' => true,

];
