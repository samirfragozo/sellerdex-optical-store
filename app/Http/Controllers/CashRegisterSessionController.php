<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opening_cash' => ['required', 'integer', 'min:0'],
        ]);

        $hasOpenSession = CashRegisterSession::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('closed_at')
            ->exists();

        if ($hasOpenSession) {
            return response()->json([
                'message' => __('app.pos.cash_session.already_open'),
            ], 422);
        }

        $session = CashRegisterSession::create([
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'opening_cash' => $data['opening_cash'],
        ]);

        return response()->json($this->present($session));
    }

    public function preview(Request $request, CashRegisterSession $cashRegisterSession): JsonResponse
    {
        abort_if($cashRegisterSession->user_id !== $request->user()->id, 403);

        if ($cashRegisterSession->closed_at !== null) {
            return response()->json([
                'message' => __('app.pos.cash_session.already_closed'),
            ], 422);
        }

        return response()->json([
            'opening_cash' => $cashRegisterSession->opening_cash,
            'expected_cash' => $cashRegisterSession->opening_cash + $this->cashCollected($cashRegisterSession),
        ]);
    }

    public function close(Request $request, CashRegisterSession $cashRegisterSession): JsonResponse
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
            'expected_cash' => $cashRegisterSession->opening_cash + $this->cashCollected($cashRegisterSession),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($this->present($cashRegisterSession->fresh()));
    }

    private function cashCollected(CashRegisterSession $cashRegisterSession): int
    {
        $cashMethodId = PaymentMethod::where('is_default', true)->value('id');

        return $cashMethodId
            ? (int) Payment::where('received_by', $cashRegisterSession->user_id)
                ->where('payment_method_id', $cashMethodId)
                ->where('created_at', '>=', $cashRegisterSession->opened_at)
                ->sum('amount')
            : 0;
    }

    /**
     * @return array<string,mixed>
     */
    private function present(CashRegisterSession $session): array
    {
        return [
            'id' => $session->id,
            'opened_at' => $session->opened_at->toIso8601String(),
            'opening_cash' => $session->opening_cash,
            'closed_at' => $session->closed_at?->toIso8601String(),
            'closed_cash' => $session->closed_cash,
            'expected_cash' => $session->expected_cash,
            'difference' => $session->difference,
        ];
    }
}
