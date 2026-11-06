<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea (o actualiza) la única cuenta administradora a partir de variables
 * de entorno. No hay registro público.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('store.admin.email');
        $password = (string) config('store.admin.password');

        if ($password === '') {
            if (AdminUser::query()->where('email', $email)->exists()) {
                return;
            }
            // Sin contraseña configurada: se genera una aleatoria y se muestra una
            // sola vez en la salida del seeder. Nunca se escribe en archivos.
            $password = Str::password(20);
            $this->command?->warn("PHARMACY_ADMIN_PASSWORD no definida. Contraseña temporal para {$email}: {$password}");
        }

        AdminUser::query()->updateOrCreate(
            ['email' => $email],
            ['name' => (string) config('store.admin.name'), 'password' => $password],
        );
    }
}
