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

        return $renderer->cashSession($cashRegisterSession);
    }
}
