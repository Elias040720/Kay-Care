<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Services\Documents\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticated, in-memory PDF downloads. No file is stored and no public
 * URL is created.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function his(Request $request, Encounter $encounter): Response
    {
        return $this->download($request, $encounter, DocumentService::HIS);
    }

    public function clinicalNote(Request $request, Encounter $encounter): Response
    {
        return $this->download($request, $encounter, DocumentService::CLINICAL_NOTE);
    }

    private function download(Request $request, Encounter $encounter, string $document): Response
    {
        Gate::authorize('downloadDocuments', $encounter);

        $pdf = $this->documents->renderPdf($document, $encounter, $request->user());

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documents->filename($document, $encounter).'"',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }
}
