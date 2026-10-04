<?php

namespace App\Http\Controllers\FiscalDocument;

use App\Http\Controllers\Controller;
use App\Models\FiscalDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfController extends Controller
{
    /** Streams the PDF of a document issued in another system (private, never public). */
    public function __invoke(FiscalDocument $fiscalDocument): StreamedResponse
    {
        Gate::authorize('view', $fiscalDocument->sale);

        abort_unless($fiscalDocument->pdf_path && Storage::disk('local')->exists($fiscalDocument->pdf_path), 404);

        return Storage::disk('local')->response($fiscalDocument->pdf_path, headers: [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }
}
