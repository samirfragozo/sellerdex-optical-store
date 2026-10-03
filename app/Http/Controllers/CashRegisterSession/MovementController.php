<?php

namespace App\Http\Controllers\CashRegisterSession;

use App\Enums\CashMovementType;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MovementController extends Controller
{
    public function __invoke(Request $request, CashRegisterSession $cashRegisterSession): JsonResponse
    {
        abort_if($cashRegisterSession->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'type' => ['required', Rule::enum(CashMovementType::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($cashRegisterSession->closed_at !== null) {
            return response()->json([
                'message' => __('app.pos.cash_session.already_closed'),
            ], 422);
        }

        $movement = $cashRegisterSession->movements()->create([
            ...$data,
            'company_id' => $cashRegisterSession->company_id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($movement, 201);
    }
}
