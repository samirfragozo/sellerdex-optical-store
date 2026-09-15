<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
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

        return response()->json($session->toSummary());
    }
}
