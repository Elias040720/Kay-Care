<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates demo staff accounts from environment variables
 * (DEMO_NURSE_EMAIL / DEMO_NURSE_PASSWORD, DEMO_ADMIN_EMAIL / DEMO_ADMIN_PASSWORD).
 * Credentials are never hardcoded: missing values skip the account.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['nurse' => UserRole::Nurse, 'admin' => UserRole::Admin] as $key => $role) {
            $config = config("kaycare.demo.{$key}");
            $email = is_string($config['email'] ?? null) ? mb_strtolower(trim($config['email'])) : '';
            $password = is_string($config['password'] ?? null) ? $config['password'] : '';

            if ($email === '' || $password === '') {
                $this->command?->warn("Cuenta demo '{$key}' omitida: defina DEMO_".strtoupper($key).'_EMAIL y DEMO_'.strtoupper($key).'_PASSWORD en .env');

                continue;
            }

            if (mb_strlen($password) < 12) {
                $this->command?->warn("Cuenta demo '{$key}' omitida: la contraseña debe tener al menos 12 caracteres.");

                continue;
            }

            $user = User::query()->firstOrNew(['email' => $email]);
            $user->fill(['name' => $config['name'], 'email' => $email, 'password' => $password]);
            $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()]);
            $user->save();

            $this->command?->info("Cuenta demo '{$key}' lista: {$email}");
        }
    }
}
