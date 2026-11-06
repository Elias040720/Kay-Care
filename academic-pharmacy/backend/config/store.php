<?php

/*
| Configuración propia de Kay Care Farmacia (tienda demostrativa).
*/

return [

    'notice' => 'PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES',

    'currency' => 'PEN',

    'rate_limits' => [
        'store' => (int) env('RATE_LIMIT_STORE', 120),
        'checkout' => (int) env('RATE_LIMIT_CHECKOUT', 10),
        'admin' => (int) env('RATE_LIMIT_ADMIN', 240),
        'login' => (int) env('RATE_LIMIT_LOGIN', 5),
    ],

    // Cuenta administradora inicial (creada por AdminUserSeeder).
    // La contraseña NUNCA se versiona: llega por variable de entorno o
    // secreto de Azure Container Apps.
    'admin' => [
        'name' => env('PHARMACY_ADMIN_NAME', 'Administración Demo'),
        'email' => env('PHARMACY_ADMIN_EMAIL', 'admin@farmacia.demo'),
        'password' => env('PHARMACY_ADMIN_PASSWORD'),
    ],

];
