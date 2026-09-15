<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\DocumentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __invoke(Sale $sale, DocumentRenderer $renderer): View
    {
        Gate::authorize('view', $sale);

        return $renderer->invoice($sale);
    }
}
