<?php

namespace App\Http\Controllers\LensOrder;

use App\Http\Controllers\Controller;
use App\Models\LensOrder;
use App\Services\DocumentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function __invoke(LensOrder $lensOrder, DocumentRenderer $renderer): View
    {
        Gate::authorize('view', $lensOrder);

        return $renderer->labOrder($lensOrder);
    }
}
