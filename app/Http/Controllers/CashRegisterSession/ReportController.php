<?php

namespace App\Http\Controllers\CashRegisterSession;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Services\DocumentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function __invoke(CashRegisterSession $cashRegisterSession, DocumentRenderer $renderer): View
    {
        Gate::authorize('view', $cashRegisterSession);

        // An open session's report carries running totals, which a blind count keeps from the cashier.
        abort_if($cashRegisterSession->closed_at === null && ! auth()->user()->can('View:CashRegisterSession'), 404);

        return $renderer->cashSession($cashRegisterSession);
    }
}
