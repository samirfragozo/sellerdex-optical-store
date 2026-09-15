<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\DocumentRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FormulaPdfController extends Controller
{
    public function __invoke(Prescription $prescription, DocumentRenderer $renderer): Response
    {
        Gate::authorize('view', $prescription);

        return $renderer->formulaPdf($prescription);
    }
}
