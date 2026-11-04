<?php

namespace App\Services\Documents;

use App\Models\Encounter;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cred\AgeCalculationService;
use App\Services\His\HisValidationService;
use App\Support\IsoDateTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Renders demonstrative PDFs in memory. Nothing is written to disk or to a
 * public directory and no permanent URL is created. Templates are resolved
 * from config('kaycare.documents.templates') so they can be replaced by
 * institution-approved formats without touching this code.
 */
class DocumentService
{
    public const HIS = 'his';

    public const CLINICAL_NOTE = 'clinical-note';

    public function __construct(
        private readonly AgeCalculationService $ages,
        private readonly HisValidationService $validator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed> view model shared by all templates
     */
    public function viewData(Encounter $encounter, User $user): array
    {
        $encounter->loadMissing(['patient', 'responsible', 'hisLines.item.version', 'hisLines.item.rules']);
        $tz = (string) config('kaycare.timezone');
        $validation = $this->validator->evaluate($encounter);

        return [
            'encounter' => $encounter,
            'patient' => $encounter->patient,
            'lines' => $encounter->hisLines,
            'age' => $this->ages->forEncounter($encounter),
            'occurredAt' => IsoDateTime::format($encounter->occurred_at, $encounter->occurred_at_offset),
            'occurredLocal' => $encounter->localOccurredAt()->format('d/m/Y H:i').' (UTC'.$encounter->occurred_at_offset.')',
            'generatedAt' => now($tz)->format('d/m/Y H:i:s').' ('.$tz.')',
            'generatedBy' => $user->name,
            'validation' => $validation,
            'watermark' => (string) config('kaycare.documents.watermark'),
            'recordId' => 'DEMO-'.strtoupper(substr($encounter->id, 0, 8)),
        ];
    }

    public function renderHtml(string $document, Encounter $encounter, User $user): string
    {
        return View::make($this->template($document), $this->viewData($encounter, $user))->render();
    }

    /** @return string binary PDF */
    public function renderPdf(string $document, Encounter $encounter, User $user): string
    {
        // The template <title> (which includes the DEMO watermark text) becomes
        // the PDF /Title metadata.
        $output = Pdf::loadView($this->template($document), $this->viewData($encounter, $user))
            ->setPaper('a4', 'portrait')
            ->output();

        $this->audit->log(AuditLogger::DOCUMENT_GENERATED, $encounter, [
            'document' => $document,
            'template' => $this->template($document),
        ], $user);

        return $output;
    }

    public function filename(string $document, Encounter $encounter): string
    {
        return sprintf('kaycare-demo-%s-%s.pdf', $document, substr($encounter->id, 0, 8));
    }

    private function template(string $document): string
    {
        $template = config("kaycare.documents.templates.{$document}");

        if (! is_string($template) || ! View::exists($template)) {
            throw new InvalidArgumentException("Plantilla de documento no configurada: {$document}");
        }

        return $template;
    }
}
