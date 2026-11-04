<?php

namespace Tests\Feature;

use App\Models\HisLine;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    private function encounterWithLine(): array
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2026-01-15', $user), '2026-09-01 09:00', $user, [
            'weight_kg' => '8.100', 'height_cm' => '70.0', 'hb_g_dl' => '11.5',
            'clinical_note' => 'Nota clínica ficticia para el documento.',
        ]);
        $line = new HisLine(['catalog_item_id' => $this->catalogItem('DEMO-CRED-M1')->id, 'diagnosis_type' => 'D', 'lab' => '7', 'position' => 0]);
        $line->forceFill(['encounter_id' => $encounter->id, 'confirmed_by' => $user->id, 'confirmed_at' => now()])->save();

        return [$user, $encounter];
    }

    public function test_his_pdf_is_generated_with_demo_marking(): void
    {
        [, $encounter] = $this->encounterWithLine();
        $publicBefore = File::exists(storage_path('app/public')) ? count(File::allFiles(storage_path('app/public'))) : 0;

        $response = $this->spa()->get("/api/v1/encounters/{$encounter->id}/documents/his.pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        // PDF metadata strings are UTF-16BE encoded by dompdf.
        $this->assertStringContainsString(mb_convert_encoding('SOLO DEMOSTRACIÓN — NO VÁLIDO PARA USO CLÍNICO', 'UTF-16BE', 'UTF-8'), $pdf);

        // Nothing is written to a public directory.
        $publicAfter = File::exists(storage_path('app/public')) ? count(File::allFiles(storage_path('app/public'))) : 0;
        $this->assertSame($publicBefore, $publicAfter);
        $this->assertDatabaseHas('audit_logs', ['event' => 'document.generated', 'auditable_id' => $encounter->id]);
    }

    public function test_clinical_note_pdf_is_generated(): void
    {
        [, $encounter] = $this->encounterWithLine();

        $response = $this->spa()->get("/api/v1/encounters/{$encounter->id}/documents/clinical-note.pdf");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString(mb_convert_encoding('SOLO DEMOSTRACIÓN', 'UTF-16BE', 'UTF-8'), $response->getContent());
    }

    public function test_templates_contain_watermark_and_unverified_warnings(): void
    {
        [$user, $encounter] = $this->encounterWithLine();
        $service = app(DocumentService::class);

        $his = $service->renderHtml(DocumentService::HIS, $encounter->fresh(), $user);
        $this->assertStringContainsString('SOLO DEMOSTRACIÓN — NO VÁLIDO PARA USO CLÍNICO', $his);
        $this->assertStringContainsString('DEMO-CRED-M1', $his);
        $this->assertStringContainsString('NO verificado', $his);
        $this->assertStringContainsString('8.1', $his);
        $this->assertStringContainsString('70', $his);

        $note = $service->renderHtml(DocumentService::CLINICAL_NOTE, $encounter->fresh(), $user);
        $this->assertStringContainsString('SOLO DEMOSTRACIÓN — NO VÁLIDO PARA USO CLÍNICO', $note);
        $this->assertStringContainsString('Nota clínica ficticia para el documento.', $note);
        $this->assertStringContainsString('no firmado', mb_strtolower($note));
    }

    public function test_pdf_download_requires_authentication(): void
    {
        [, $encounter] = $this->encounterWithLine();
        $this->app['auth']->forgetGuards();
        auth()->guard('web')->logout();

        $this->spa()->get("/api/v1/encounters/{$encounter->id}/documents/his.pdf")
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_pdf_for_unknown_encounter_returns_json_404(): void
    {
        $this->actingAsNurse();

        $this->spa()->get('/api/v1/encounters/9b2f4c1e-8d3a-4f6b-9c2d-1e5f7a8b9c0d/documents/his.pdf')
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }
}
