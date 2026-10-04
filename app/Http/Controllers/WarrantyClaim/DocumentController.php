<?php

namespace App\Http\Controllers\WarrantyClaim;

use App\Http\Controllers\Controller;
use App\Models\WarrantyClaim;
use App\Services\DocumentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function __invoke(WarrantyClaim $warrantyClaim, DocumentRenderer $renderer): View
    {
        Gate::authorize('view', $warrantyClaim);

        return $renderer->warrantyClaim($warrantyClaim);
    }
}
