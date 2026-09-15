<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\DocumentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class FormulaController extends Controller
{
    public function __invoke(Prescription $prescription, DocumentRenderer $renderer): View
    {
        Gate::authorize('view', $prescription);

        return $renderer->formula($prescription);
    }
}
