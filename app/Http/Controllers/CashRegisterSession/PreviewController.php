<?php

namespace App\Http\Controllers\CashRegisterSession;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    public function __invoke(Request $request, CashRegisterSession $cashRegisterSession): JsonResponse
    {
        abort_if($cashRegisterSession->user_id !== $request->user()->id, 403);

        if ($cashRegisterSession->closed_at !== null) {
            return response()->json([
                'message' => __('app.pos.cash_session.already_closed'),
            ], 422);
        }

        $blind = (bool) Company::withoutGlobalScopes()->findOrFail($cashRegisterSession->company_id)->blind_cash_count;
        $expected = $cashRegisterSession->expectedByMethod();
        $cashMethodId = $cashRegisterSession->cashMethodId();
        $names = PaymentMethod::withoutGlobalScopes()->whereIn('id', array_keys($expected))->pluck('name', 'id');

        return response()->json([
            'opening_cash' => $cashRegisterSession->opening_cash,
            'blind' => $blind,
            'methods' => collect($expected)->map(fn (int $amount, int $id): array => [
                'payment_method_id' => $id,
                'name' => $names[$id] ?? null,
                'is_cash' => $id === $cashMethodId,
                'expected' => $blind ? null : $amount,
            ])->values(),
        ]);
    }
}
