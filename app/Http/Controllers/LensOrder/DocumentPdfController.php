<?php

namespace App\Http\Controllers\LensOrder;

use App\Http\Controllers\Controller;
use App\Models\LensOrder;
use App\Services\DocumentRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DocumentPdfController extends Controller
{
    public function __invoke(LensOrder $lensOrder, DocumentRenderer $renderer): Response
    {
        Gate::authorize('view', $lensOrder);

        return $renderer->labOrderPdf($lensOrder);
    }
}
