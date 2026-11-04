<?php

namespace Tests;

use App\Models\Encounter;
use App\Models\HisCatalogItem;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\HisDemoCatalogSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const SPA_ORIGIN = 'http://localhost:5173';

    /** Headers a browser sends from the Vue SPA (stateful Sanctum request). */
    protected function spa(): static
    {
        return $this->withHeaders([
            'Origin' => self::SPA_ORIGIN,
            'Referer' => self::SPA_ORIGIN.'/',
            'Accept' => 'application/json',
        ]);
    }

    protected function nurse(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function actingAsNurse(?User $user = null): User
    {
        $user ??= $this->nurse();
        // The Sanctum RequestGuard caches the resolved user per app instance.
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');

        return $user;
    }

    protected function seedCatalog(): void
    {
        $this->seed(HisDemoCatalogSeeder::class);
    }

    protected function catalogItem(string $code): HisCatalogItem
    {
        return HisCatalogItem::query()->where('code', $code)->firstOrFail();
    }

    protected function patientBornOn(string $date, ?User $author = null): Patient
    {
        $author ??= User::factory()->create();

        return Patient::factory()->bornOn($date)->create([
            'created_by' => $author->id,
            'updated_by' => $author->id,
        ]);
    }

    /** Encounter at a local Lima wall-clock time ("Y-m-d H:i"). */
    protected function encounterAt(Patient $patient, string $localDateTime, User $responsible, array $attributes = []): Encounter
    {
        return Encounter::factory()->occurredAtLocal($localDateTime)->create(array_merge([
            'patient_id' => $patient->id,
            'responsible_user_id' => $responsible->id,
            'created_by' => $responsible->id,
            'updated_by' => $responsible->id,
        ], $attributes));
    }

    /** @param  array<string, mixed>  $data */
    protected function assertExactKeys(array $expected, array $data, string $context = ''): void
    {
        $keys = array_keys($data);
        sort($keys);
        sort($expected);
        $this->assertSame($expected, $keys, "Unexpected key set {$context}");
    }
}
