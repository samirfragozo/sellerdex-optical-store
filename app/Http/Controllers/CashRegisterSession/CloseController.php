<?php

namespace App\Http\Controllers\CashRegisterSession;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloseController extends Controller
{
    public function __invoke(Request $request, CashRegisterSession $cashRegisterSession): JsonResponse
    {
        abort_if($cashRegisterSession->user_id !== $request->user()->id, 403);

        if ($cashRegisterSession->closed_at !== null) {
            return response()->json([
                'message' => __('app.pos.cash_session.already_closed'),
            ], 422);
        }

        $data = $request->validate([
            'closed_cash' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $cashRegisterSession->update([
            'closed_at' => now(),
            'closed_cash' => $data['closed_cash'],
            'expected_cash' => $cashRegisterSession->expectedCash(),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($cashRegisterSession->fresh()->toSummary());
    }
}
