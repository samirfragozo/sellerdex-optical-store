<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\Sale;
use App\Services\DocumentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentRenderer $renderer) {}

    public function invoice(Sale $sale): View
    {
        Gate::authorize('view', $sale);

        return $this->renderer->invoice($sale);
    }

    public function invoicePdf(Sale $sale): Response
    {
        Gate::authorize('view', $sale);

        return $this->renderer->invoicePdf($sale);
    }

    public function formula(Prescription $prescription): View
    {
        Gate::authorize('view', $prescription);

        return $this->renderer->formula($prescription);
    }

    public function formulaPdf(Prescription $prescription): Response
    {
        Gate::authorize('view', $prescription);

        return $this->renderer->formulaPdf($prescription);
    }
}
