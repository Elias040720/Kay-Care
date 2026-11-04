<?php

use App\Models\AdminUser;

/*
|--------------------------------------------------------------------------
| Autenticación — Kay Care Farmacia (proyecto académico)
|--------------------------------------------------------------------------
|
| Solo existe un tipo de identidad: el administrador de la tienda
| demostrativa. No hay registro público ni cuentas de compradores.
| La tabla `admin_users` vive en la base de datos propia de la farmacia y
| no se comparte con el sistema clínico Kay Care Enfermería.
|
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'admin'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => AdminUser::class,
        ],
    ],

    // No se expone ningún flujo de recuperación de contraseña: la cuenta
    // administradora se crea por seeder/variables de entorno.
    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
