<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\RecordRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    private const PATIENT_KEYS = [
        'id', 'chart_number', 'first_name', 'last_name', 'birth_date', 'sex',
        'document_type', 'document_number', 'locality', 'created_at', 'updated_at',
    ];

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'chart_number' => 'hc-0001',
            'first_name' => '  Ana   María ',
            'last_name' => 'Ficticia Prueba',
            'birth_date' => now('America/Lima')->subMonths(5)->toDateString(),
            'sex' => 'F',
            'document_type' => null,
            'document_number' => null,
            'locality' => 'Localidad Ficticia',
        ], $overrides);
    }

    public function test_creates_a_patient_and_returns_contract_shape(): void
    {
        $user = $this->actingAsNurse();

        $response = $this->spa()->postJson('/api/v1/patients', $this->payload());

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertExactKeys(self::PATIENT_KEYS, $data);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $data['id']);
        $this->assertSame('HC-0001', $data['chart_number']);
        $this->assertSame('Ana María', $data['first_name']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['birth_date']);
        $this->assertNull($data['document_type']);

        $patient = Patient::query()->findOrFail($data['id']);
        $this->assertSame($user->id, $patient->created_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'patient.created', 'auditable_id' => $patient->id, 'user_id' => $user->id]);
    }

    public function test_newborn_can_be_registered_without_dni(): void
    {
        $this->actingAsNurse();

        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'birth_date' => now('America/Lima')->toDateString(),
            'document_type' => null,
            'document_number' => null,
        ]))->assertCreated();
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        $this->actingAsNurse();

        $payload = $this->payload();
        unset($payload['document_type'], $payload['document_number'], $payload['locality']);

        $this->spa()->postJson('/api/v1/patients', $payload)
            ->assertCreated()
            ->assertJsonPath('data.locality', null);
    }

    public function test_validates_required_fields_and_formats(): void
    {
        $this->actingAsNurse();

        $this->spa()->postJson('/api/v1/patients', [
            'birth_date' => '10/05/2025',
            'sex' => 'Z',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['chart_number', 'first_name', 'last_name', 'birth_date', 'sex']]);
    }

    public function test_rejects_future_birth_date_and_invalid_dni(): void
    {
        $this->actingAsNurse();

        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'birth_date' => now('America/Lima')->addDays(2)->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors('birth_date');

        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'document_type' => 'DNI', 'document_number' => '1234',
        ]))->assertStatus(422)->assertJsonValidationErrors('document_number');

        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'document_type' => 'DNI', 'document_number' => null,
        ]))->assertStatus(422)->assertJsonValidationErrors('document_number');

        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'document_type' => null, 'document_number' => '90000001',
        ]))->assertStatus(422)->assertJsonValidationErrors('document_type');
    }

    public function test_prevents_duplicates_by_chart_number_and_document(): void
    {
        $this->actingAsNurse();

        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'document_type' => 'DNI', 'document_number' => '90000001',
        ]))->assertCreated();

        $this->spa()->postJson('/api/v1/patients', $this->payload(['chart_number' => 'HC-0001 ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('chart_number');

        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'chart_number' => 'HC-0002', 'document_type' => 'dni', 'document_number' => '9000 0001',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_number');

        // Same number with a different document type is a different identifier.
        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'chart_number' => 'HC-0003', 'document_type' => 'CE', 'document_number' => '90000001',
        ]))->assertCreated();

        // Several patients without document are allowed.
        $this->spa()->postJson('/api/v1/patients', $this->payload(['chart_number' => 'HC-0004']))->assertCreated();
        $this->spa()->postJson('/api/v1/patients', $this->payload(['chart_number' => 'HC-0005']))->assertCreated();

        $this->assertDatabaseCount('patients', 4);
    }

    public function test_mass_assignment_of_id_is_rejected(): void
    {
        $this->actingAsNurse();

        $this->spa()->postJson('/api/v1/patients', $this->payload([
            'id' => '9b2f4c1e-8d3a-4f6b-9c2d-1e5f7a8b9c0d',
        ]))->assertStatus(422)->assertJsonValidationErrors('id');
    }

    public function test_searches_and_paginates_with_contract_meta(): void
    {
        $user = $this->actingAsNurse();
        Patient::factory()->count(18)->create(['created_by' => $user->id, 'updated_by' => $user->id]);
        Patient::factory()->withDni('91234567')->create([
            'first_name' => 'Rosalía', 'last_name' => 'Quispe Ficticia', 'chart_number' => 'HC-7788',
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        $page = $this->spa()->getJson('/api/v1/patients?page=2')->assertOk();
        $this->assertExactKeys(['data', 'meta'], $page->json());
        $this->assertSame(['current_page' => 2, 'last_page' => 2, 'per_page' => 15, 'total' => 19], $page->json('meta'));
        $this->assertCount(4, $page->json('data'));

        foreach (['rosal', 'QUISPE', 'hc-77', '9123', 'rosalía quispe'] as $term) {
            $result = $this->spa()->getJson('/api/v1/patients?search='.urlencode($term))->assertOk();
            $this->assertSame(1, $result->json('meta.total'), "search: {$term}");
            $this->assertSame('HC-7788', $result->json('data.0.chart_number'));
        }

        // LIKE wildcards are escaped.
        $this->spa()->getJson('/api/v1/patients?search=%25')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_search_input_is_bounded(): void
    {
        $this->actingAsNurse();

        $this->spa()->getJson('/api/v1/patients?search='.str_repeat('a', 101))
            ->assertStatus(422)->assertJsonValidationErrors('search');
        $this->spa()->getJson('/api/v1/patients?page=0')
            ->assertStatus(422)->assertJsonValidationErrors('page');
    }

    public function test_shows_patient_and_returns_404_for_unknown_or_invalid_ids(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-01-10', $user);

        $this->spa()->getJson("/api/v1/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.birth_date', '2026-01-10');

        $this->spa()->getJson('/api/v1/patients/9b2f4c1e-8d3a-4f6b-9c2d-1e5f7a8b9c0d')
            ->assertStatus(404)->assertJsonStructure(['message']);
        $this->spa()->getJson('/api/v1/patients/not-a-uuid')
            ->assertStatus(404)->assertJsonStructure(['message']);
    }

    public function test_partial_update_keeps_revision_history(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-01-10', $user);
        $oldLocality = $patient->locality;

        $this->spa()->patchJson("/api/v1/patients/{$patient->id}", ['locality' => 'Otra Localidad Ficticia'])
            ->assertOk()
            ->assertJsonPath('data.locality', 'Otra Localidad Ficticia')
            ->assertJsonPath('data.chart_number', $patient->chart_number);

        $revision = RecordRevision::query()->where('revisionable_id', $patient->id)->firstOrFail();
        $this->assertSame(1, $revision->revision);
        $this->assertSame(['locality'], $revision->changed_fields);
        $this->assertSame($oldLocality, $revision->snapshot['locality']);
        $this->assertSame(2, $patient->fresh()->revision);

        $log = AuditLog::query()->where('event', 'patient.updated')->firstOrFail();
        $this->assertSame(['locality'], $log->metadata['changed_fields']);
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_noop_update_creates_no_revision(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-01-10', $user);

        $this->spa()->patchJson("/api/v1/patients/{$patient->id}", ['birth_date' => '2026-01-10'])->assertOk();

        $this->assertDatabaseCount('record_revisions', 0);
    }

    public function test_update_rejects_duplicate_chart_number_and_document_pair_rules(): void
    {
        $user = $this->actingAsNurse();
        $a = Patient::factory()->create(['chart_number' => 'HC-A', 'created_by' => $user->id, 'updated_by' => $user->id]);
        $b = Patient::factory()->withDni('90000002')->create(['chart_number' => 'HC-B', 'created_by' => $user->id, 'updated_by' => $user->id]);

        $this->spa()->patchJson("/api/v1/patients/{$a->id}", ['chart_number' => 'HC-B'])
            ->assertStatus(422)->assertJsonValidationErrors('chart_number');

        // Removing only the type leaves an orphan number.
        $this->spa()->patchJson("/api/v1/patients/{$b->id}", ['document_type' => null])
            ->assertStatus(422)->assertJsonValidationErrors('document_type');

        // Keeping its own values is not a duplicate.
        $this->spa()->patchJson("/api/v1/patients/{$b->id}", ['document_number' => '90000002', 'chart_number' => 'HC-B'])
            ->assertOk();
    }

    public function test_birth_date_cannot_move_after_recorded_encounters(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2025-06-01', $user);
        $this->encounterAt($patient, '2025-07-01 10:00', $user);

        $this->spa()->patchJson("/api/v1/patients/{$patient->id}", ['birth_date' => '2025-07-15'])
            ->assertStatus(422)->assertJsonValidationErrors('birth_date');
    }

    public function test_there_is_no_delete_endpoint(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2025-06-01', $user);

        $this->spa()->deleteJson("/api/v1/patients/{$patient->id}")->assertStatus(404);
        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
    }
}
