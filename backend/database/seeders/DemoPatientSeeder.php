<?php

namespace Database\Seeders;

use App\Enums\EncounterStatus;
use App\Models\Encounter;
use App\Models\HisCatalogItem;
use App\Models\HisLine;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FICTITIOUS patients for the demo. Names are invented, there are no
 * identity documents and every chart number starts with "DEMO-".
 */
class DemoPatientSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->orderBy('created_at')->first();

        if ($author === null) {
            $this->command?->warn('Pacientes demo omitidos: no existe ningún usuario (configure las cuentas demo).');

            return;
        }

        $today = CarbonImmutable::now((string) config('kaycare.timezone'))->startOfDay();

        $patients = [
            ['chart_number' => 'DEMO-0001', 'first_name' => 'Lucía', 'last_name' => 'Ficticia Demo', 'birth_date' => $today->subDays(10), 'sex' => 'F'],
            ['chart_number' => 'DEMO-0002', 'first_name' => 'Mateo', 'last_name' => 'Ejemplo Prueba', 'birth_date' => $today->subMonths(7)->subDays(3), 'sex' => 'M'],
            ['chart_number' => 'DEMO-0003', 'first_name' => 'Valeria', 'last_name' => 'Simulada Demo', 'birth_date' => $today->subYears(3)->subMonths(2), 'sex' => 'F'],
        ];

        DB::transaction(function () use ($patients, $author, $today) {
            foreach ($patients as $data) {
                $patient = Patient::query()->firstOrNew(['chart_number' => $data['chart_number']]);
                $patient->fill([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'birth_date' => $data['birth_date']->toDateString(),
                    'sex' => $data['sex'],
                    'document_type' => null,
                    'document_number' => null,
                    'locality' => 'Localidad Ficticia',
                ]);
                $patient->forceFill(['created_by' => $patient->created_by ?? $author->id, 'updated_by' => $author->id]);
                $patient->save();
            }

            // One prior fictitious encounter with a confirmed control so the
            // demo can show how the control number is derived from history.
            $mateo = Patient::query()->where('chart_number', 'DEMO-0002')->firstOrFail();
            $item = HisCatalogItem::query()->where('code', 'DEMO-CRED-M1')->first();

            if ($item !== null && ! $mateo->encounters()->exists()) {
                $encounter = new Encounter([
                    'patient_id' => $mateo->id,
                    'type' => 'CRED',
                    'clinical_note' => 'Atención FICTICIA de demostración.',
                    'weight_kg' => null,
                    'height_cm' => null,
                    'hb_g_dl' => null,
                ]);
                $encounter->forceFill([
                    'occurred_at' => $today->subMonth()->setTime(9, 0)->shiftTimezone('-05:00')->utc(),
                    'occurred_at_offset' => '-05:00',
                    'status' => EncounterStatus::Draft,
                    'responsible_user_id' => $author->id,
                    'created_by' => $author->id,
                    'updated_by' => $author->id,
                ])->save();

                $line = new HisLine(['catalog_item_id' => $item->id, 'diagnosis_type' => 'D', 'lab' => '6', 'position' => 0]);
                $line->forceFill([
                    'encounter_id' => $encounter->id,
                    'confirmed_by' => $author->id,
                    'confirmed_at' => now(),
                ])->save();
            }
        });

        $this->command?->info('Pacientes FICTICIOS demo creados (DEMO-0001..0003).');
    }
}
