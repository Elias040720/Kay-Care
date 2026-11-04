<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo seeding only. Refuses to run in production or outside demo mode
     * so fictitious data and the DEMO catalog never reach a real deployment.
     */
    public function run(): void
    {
        if (app()->isProduction() || config('kaycare.mode') !== 'demo') {
            throw new RuntimeException('Los datos demo solo pueden sembrarse con KAYCARE_MODE=demo y APP_ENV distinto de production.');
        }

        $this->call([
            HisDemoCatalogSeeder::class,
            DemoUserSeeder::class,
            DemoPatientSeeder::class,
        ]);
    }
}
