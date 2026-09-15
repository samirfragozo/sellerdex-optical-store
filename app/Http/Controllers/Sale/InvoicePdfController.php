<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\DocumentRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InvoicePdfController extends Controller
{
    public function __invoke(Sale $sale, DocumentRenderer $renderer): Response
    {
        Gate::authorize('view', $sale);

        return $renderer->invoicePdf($sale);
    }
}
