<?php

namespace Database\Factories;

use App\Enums\Sex;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only factory: fictitious patients, never real people.
 *
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chart_number' => 'TEST-'.Str::upper(Str::random(8)),
            'document_type' => null,
            'document_number' => null,
            'first_name' => $this->faker->randomElement(['Ana', 'Luis', 'Rosa', 'Mario', 'Elena', 'Jorge']),
            'last_name' => 'Ficticio '.$this->faker->randomElement(['Prueba', 'Ejemplo', 'Demo']),
            'birth_date' => now('America/Lima')->subMonths(8)->toDateString(),
            'sex' => $this->faker->randomElement(Sex::cases()),
            'locality' => 'Localidad Ficticia',
            'created_by' => User::factory(),
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }

    public function bornOn(string $date): static
    {
        return $this->state(fn () => ['birth_date' => $date]);
    }

    public function withDni(?string $number = null): static
    {
        return $this->state(fn () => [
            'document_type' => 'DNI',
            // Fictitious test value, only ever used in the in-memory test DB.
            'document_number' => $number ?? '9'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
        ]);
    }
}
