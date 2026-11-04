<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Test-only factory. Generates fictitious staff accounts.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => 'Usuario Prueba '.Str::upper(Str::random(4)),
            'email' => 'user-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password-de-prueba'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Nurse,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
