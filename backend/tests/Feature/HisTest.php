<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Encounter;
use App\Models\HisCatalogItem;
use App\Models\HisCatalogVersion;
use App\Models\HisLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HisTest extends TestCase
{
    use RefreshDatabase;

    private const SUGGESTION_KEYS = [
        'catalog_item_id', 'label', 'code', 'diagnosis_type_suggested', 'lab_suggested',
        'verification_status', 'source', 'rationale', 'rule_code', 'catalog_version',
    ];

    private const LINE_KEYS = ['id', 'catalog_item_id', 'label', 'code', 'diagnosis_type', 'lab', 'verification_status'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    /** @return list<string> */
    private function codes(array $suggestions): array
    {
        $codes = array_column($suggestions, 'code');
        sort($codes);

        return $codes;
    }

    private function confirm(Encounter $encounter, string $code, ?string $type, ?string $lab): void
    {
        $line = new HisLine(['catalog_item_id' => $this->catalogItem($code)->id, 'diagnosis_type' => $type, 'lab' => $lab, 'position' => 0]);
        $line->forceFill(['encounter_id' => $encounter->id, 'confirmed_by' => $encounter->responsible_user_id, 'confirmed_at' => now()])->save();
    }

    public function test_newborn_suggestions_are_age_based_and_never_preselected(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-10-20', $user), '2026-10-30 09:00', $user);

        $response = $this->spa()->getJson("/api/v1/encounters/{$encounter->id}/his-suggestions")->assertOk();

        $data = $response->json('data');
        $this->assertSame(['DEMO-CONS-LM', 'DEMO-CRED-RN', 'DEMO-DESARROLLO', 'DEMO-TAMIZ-NEO'], $this->codes($data));

        foreach ($data as $suggestion) {
            $this->assertExactKeys(self::SUGGESTION_KEYS, $suggestion);
            $this->assertSame('draft', $suggestion['verification_status']);
            $this->assertSame('demo', $suggestion['source']);
            $this->assertStringStartsWith('DEMO-', $suggestion['code']);
            $this->assertArrayNotHasKey('selected', $suggestion);
        }

        // Suggestions are never persisted as performed activities.
        $this->assertDatabaseCount('his_lines', 0);

        // Without recorded history the control number is NOT assumed.
        $control = collect($data)->firstWhere('code', 'DEMO-CRED-RN');
        $this->assertNull($control['lab_suggested']);
        $this->assertSame('D', $control['diagnosis_type_suggested']);
        $this->assertStringContainsString('no se asume', $control['rationale']);
    }

    public function test_suggestions_by_age_band(): void
    {
        $user = $this->actingAsNurse();

        $infant = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user); // 7 months
        $this->assertSame(
            ['DEMO-CONS-AC', 'DEMO-CRED-M1', 'DEMO-DESARROLLO'],
            $this->codes($this->spa()->getJson("/api/v1/encounters/{$infant->id}/his-suggestions")->json('data')),
        );

        $toddler = $this->encounterAt($this->patientBornOn('2023-01-15', $user), '2026-09-01 09:00', $user); // 3 years
        $this->assertSame(
            ['DEMO-AGUDEZA', 'DEMO-CRED-1A4', 'DEMO-DESARROLLO'],
            $this->codes($this->spa()->getJson("/api/v1/encounters/{$toddler->id}/his-suggestions")->json('data')),
        );

        $schoolAge = $this->encounterAt($this->patientBornOn('2018-01-15', $user), '2026-09-01 09:00', $user); // 8 years
        $this->assertSame(
            ['DEMO-AGUDEZA', 'DEMO-CRED-5A11'],
            $this->codes($this->spa()->getJson("/api/v1/encounters/{$schoolAge->id}/his-suggestions")->json('data')),
        );
    }

    public function test_historical_encounter_uses_age_at_that_date(): void
    {
        $user = $this->actingAsNurse();
        // Today the patient is > 1 year old, but the encounter was neonatal.
        $encounter = $this->encounterAt($this->patientBornOn('2024-03-01', $user), '2024-03-10 09:00', $user);

        $codes = $this->codes($this->spa()->getJson("/api/v1/encounters/{$encounter->id}/his-suggestions")->json('data'));

        $this->assertContains('DEMO-CRED-RN', $codes);
        $this->assertNotContains('DEMO-CRED-1A4', $codes);
    }

    public function test_measurement_based_suggestions_require_recorded_values(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-01-15', $user);

        $without = $this->encounterAt($patient, '2026-09-01 09:00', $user);
        $codes = $this->codes($this->spa()->getJson("/api/v1/encounters/{$without->id}/his-suggestions")->json('data'));
        $this->assertNotContains('DEMO-HB', $codes);
        $this->assertNotContains('DEMO-ANTRO', $codes);

        $with = $this->encounterAt($patient, '2026-09-15 09:00', $user, ['hb_g_dl' => '11.5', 'weight_kg' => '8.100']);
        $data = $this->spa()->getJson("/api/v1/encounters/{$with->id}/his-suggestions")->json('data');
        $hb = collect($data)->firstWhere('code', 'DEMO-HB');
        $this->assertSame('11.5', $hb['lab_suggested'], 'LAB comes from the recorded value, never invented');
        $this->assertNull($hb['diagnosis_type_suggested'], 'no diagnosis is inferred from measurements');
        $this->assertNotNull(collect($data)->firstWhere('code', 'DEMO-ANTRO'));
    }

    public function test_control_number_derives_from_confirmed_history_in_the_same_band(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-01-15', $user);

        $first = $this->encounterAt($patient, '2026-07-01 09:00', $user);
        $this->confirm($first, 'DEMO-CRED-M1', 'D', '5');
        // A neonatal control belongs to a different band and must not count.
        $neonatal = $this->encounterAt($patient, '2026-01-20 09:00', $user);
        $this->confirm($neonatal, 'DEMO-CRED-RN', 'D', '1');

        $current = $this->encounterAt($patient, '2026-08-01 09:00', $user);
        $control = collect($this->spa()->getJson("/api/v1/encounters/{$current->id}/his-suggestions")->json('data'))
            ->firstWhere('code', 'DEMO-CRED-M1');

        $this->assertSame('6', $control['lab_suggested']);

        // Later encounters are not "history" for an earlier one.
        $earlier = $this->encounterAt($patient, '2026-06-01 09:00', $user);
        $control = collect($this->spa()->getJson("/api/v1/encounters/{$earlier->id}/his-suggestions")->json('data'))
            ->firstWhere('code', 'DEMO-CRED-M1');
        $this->assertNull($control['lab_suggested']);
    }

    public function test_replaces_his_lines_with_confirmed_activities(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user);
        $control = $this->catalogItem('DEMO-CRED-M1');
        $development = $this->catalogItem('DEMO-DESARROLLO');

        $response = $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $control->id, 'diagnosis_type' => 'D', 'lab' => '7'],
            ['catalog_item_id' => $development->id, 'diagnosis_type' => null, 'lab' => null],
        ]])->assertOk();

        $lines = $response->json('data');
        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            $this->assertExactKeys(self::LINE_KEYS, $line);
            $this->assertSame('draft', $line['verification_status']);
        }
        $this->assertSame(['DEMO-CRED-M1', '7', 'D'], [$lines[0]['code'], $lines[0]['lab'], $lines[0]['diagnosis_type']]);

        // PUT replaces (does not append).
        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $development->id, 'diagnosis_type' => 'P', 'lab' => null],
        ]])->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, HisLine::query()->where('encounter_id', $encounter->id)->count());

        $log = AuditLog::query()->where('event', 'encounter.his_lines_replaced')->latest('created_at')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertArrayHasKey('before', $log->metadata);
        $this->assertArrayHasKey('after', $log->metadata);

        // An empty list clears the confirmed activities.
        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => []])
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_rejects_invalid_and_unknown_catalog_ids(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user);

        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => 'not-a-uuid', 'diagnosis_type' => null, 'lab' => null],
        ]])->assertStatus(422)->assertJsonValidationErrors('lines.0.catalog_item_id');

        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => '9b2f4c1e-8d3a-4f6b-9c2d-1e5f7a8b9c0d', 'diagnosis_type' => null, 'lab' => null],
        ]])->assertStatus(422)->assertJsonValidationErrors('lines.0.catalog_item_id');

        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", [])
            ->assertStatus(422)->assertJsonValidationErrors('lines');

        $this->assertDatabaseCount('his_lines', 0);
    }

    public function test_empty_lines_clear_activities_without_a_current_catalog_but_preview_remains_invalid(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user);
        $this->confirm($encounter, 'DEMO-CRED-M1', 'D', '7');
        HisCatalogVersion::query()->update(['is_active' => false]);

        $this->spa()->getJson("/api/v1/encounters/{$encounter->id}/his-suggestions")
            ->assertOk()->assertExactJson(['data' => []]);

        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => []])
            ->assertOk()->assertExactJson(['data' => []]);
        $this->assertDatabaseMissing('his_lines', ['encounter_id' => $encounter->id]);

        // An already empty record can also continue to review without a catalog.
        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => []])
            ->assertOk()->assertExactJson(['data' => []]);

        $preview = $this->spa()->getJson("/api/v1/encounters/{$encounter->id}/his-preview")
            ->assertOk()
            ->assertJsonPath('data.validated', false)
            ->assertJsonPath('data.validation_scope', 'structural')
            ->assertJsonPath('data.encounter.status', 'draft')
            ->assertJsonCount(0, 'data.lines');
        $this->assertContains(
            'ERROR: No existe un catálogo HIS vigente para la fecha de la atención.',
            $preview->json('data.warnings'),
        );
    }

    public function test_nonempty_lines_without_a_current_catalog_are_rejected_without_changing_saved_activities(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user);
        $this->confirm($encounter, 'DEMO-CRED-M1', 'D', '7');
        $saved = HisLine::query()->where('encounter_id', $encounter->id)->firstOrFail();
        HisCatalogVersion::query()->update(['is_active' => false]);

        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $this->catalogItem('DEMO-DESARROLLO')->id, 'diagnosis_type' => null, 'lab' => null],
        ]])->assertStatus(422)->assertJsonValidationErrors('lines');

        $this->assertSame(1, HisLine::query()->where('encounter_id', $encounter->id)->count());
        $this->assertDatabaseHas('his_lines', [
            'id' => $saved->id,
            'encounter_id' => $encounter->id,
            'catalog_item_id' => $saved->catalog_item_id,
            'diagnosis_type' => 'D',
            'lab' => '7',
        ]);
    }

    public function test_rejects_duplicate_lines(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user);
        $item = $this->catalogItem('DEMO-DESARROLLO');

        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $item->id, 'diagnosis_type' => 'D', 'lab' => null],
            ['catalog_item_id' => $item->id, 'diagnosis_type' => 'P', 'lab' => null],
        ]])->assertStatus(422)->assertJsonValidationErrors(['lines.0.catalog_item_id', 'lines.1.catalog_item_id']);

        $this->assertDatabaseCount('his_lines', 0);
    }

    public function test_validates_diagnosis_type_and_lab_against_the_catalog(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user);

        // Shape: diagnosis type outside P/D/R.
        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $this->catalogItem('DEMO-DESARROLLO')->id, 'diagnosis_type' => 'X', 'lab' => null],
        ]])->assertStatus(422)->assertJsonValidationErrors(['lines.0.diagnosis_type']);

        // Catalog rules: allowed types and LAB format per item.
        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $this->catalogItem('DEMO-CRED-M1')->id, 'diagnosis_type' => 'P', 'lab' => '99'],
            ['catalog_item_id' => $this->catalogItem('DEMO-CONS-AC')->id, 'diagnosis_type' => null, 'lab' => '3'],
            ['catalog_item_id' => $this->catalogItem('DEMO-HB')->id, 'diagnosis_type' => null, 'lab' => '99.9'],
        ]])->assertStatus(422)->assertJsonValidationErrors([
            'lines.0.diagnosis_type', 'lines.0.lab', 'lines.1.lab', 'lines.2.lab',
        ]);

        $this->assertDatabaseCount('his_lines', 0);
    }

    public function test_items_outside_the_catalog_in_force_are_rejected(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user);

        $old = HisCatalogVersion::query()->create([
            'code' => 'DEMO-OLD', 'name' => 'Old', 'source' => 'demo', 'verification_status' => 'draft',
            'valid_from' => '2010-01-01', 'valid_to' => '2014-12-31', 'is_active' => true,
        ]);
        $oldItem = HisCatalogItem::query()->create([
            'catalog_version_id' => $old->id, 'code' => 'DEMO-OLD-1', 'label' => 'Old', 'category' => 'control',
            'allowed_diagnosis_types' => ['D'], 'lab_mode' => 'none', 'verification_status' => 'draft', 'source' => 'demo',
        ]);

        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $oldItem->id, 'diagnosis_type' => 'D', 'lab' => null],
        ]])->assertStatus(422)->assertJsonValidationErrors('lines.0.catalog_item_id');
    }

    public function test_only_the_responsible_professional_or_admin_can_change_lines(): void
    {
        $author = $this->nurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $author), '2026-09-01 09:00', $author);
        $payload = ['lines' => [['catalog_item_id' => $this->catalogItem('DEMO-DESARROLLO')->id, 'diagnosis_type' => null, 'lab' => null]]];

        $this->actingAsNurse();
        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", $payload)->assertStatus(403);

        $this->actingAsNurse(User::factory()->admin()->create());
        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", $payload)->assertOk();
    }

    public function test_preview_reports_structural_validation_and_demo_warnings(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-01-15', $user);
        $encounter = $this->encounterAt($patient, '2026-09-01 09:00', $user);

        $empty = $this->spa()->getJson("/api/v1/encounters/{$encounter->id}/his-preview")->assertOk();
        $this->assertExactKeys(['patient', 'encounter', 'lines', 'warnings', 'validated', 'validation_scope'], $empty->json('data'));
        $this->assertFalse($empty->json('data.validated'));
        $this->assertSame($patient->id, $empty->json('data.patient.id'));
        $this->assertSame($encounter->id, $empty->json('data.encounter.id'));

        // Control without number -> still not valid.
        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $this->catalogItem('DEMO-CRED-M1')->id, 'diagnosis_type' => 'D', 'lab' => null],
        ]])->assertOk();
        $this->assertFalse($this->spa()->getJson("/api/v1/encounters/{$encounter->id}/his-preview")->json('data.validated'));

        $this->spa()->putJson("/api/v1/encounters/{$encounter->id}/his-lines", ['lines' => [
            ['catalog_item_id' => $this->catalogItem('DEMO-CRED-M1')->id, 'diagnosis_type' => 'D', 'lab' => '7'],
            ['catalog_item_id' => $this->catalogItem('DEMO-AGUDEZA')->id, 'diagnosis_type' => null, 'lab' => null],
        ]])->assertOk();

        $preview = $this->spa()->getJson("/api/v1/encounters/{$encounter->id}/his-preview")->assertOk();
        $this->assertTrue($preview->json('data.validated'));
        $this->assertSame('structural', $preview->json('data.validation_scope'));
        $this->assertCount(2, $preview->json('data.lines'));

        $warnings = implode("\n", $preview->json('data.warnings'));
        $this->assertStringContainsString('DEMOSTRATIVOS', $warnings);
        $this->assertStringContainsString('borrador', $warnings);
        $this->assertStringContainsString('DEMO-AGUDEZA', $warnings, 'activity not applicable to the age is flagged');
    }
}
