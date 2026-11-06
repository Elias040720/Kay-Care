<?php

/*
| CORS restrictivo. En la arquitectura de referencia el frontend (nginx)
| actúa como proxy inverso de /api, de modo que el navegador habla con un
| único origen y CORS no es necesario. Si se expone el backend en otro
| origen, solo se aceptan los orígenes listados en PHARMACY_ALLOWED_ORIGINS.
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('PHARMACY_ALLOWED_ORIGINS', 'http://localhost:8088'))
)));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'Idempotency-Key'],

    'exposed_headers' => ['Idempotency-Replayed', 'X-Request-Id'],

    'max_age' => 600,

    'supports_credentials' => true,

];
