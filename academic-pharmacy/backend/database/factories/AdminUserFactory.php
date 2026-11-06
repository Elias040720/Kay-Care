<?php

namespace Database\Factories;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdminUser> */
class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    public function definition(): array
    {
        return [
            'name' => 'Admin Prueba',
            'email' => $this->faker->unique()->userName().'@farmacia.test',
            'password' => 'Secreta-Prueba-2026',
            'remember_token' => null,
        ];
    }
}
