<?php

namespace App\Http\Controllers\CashRegisterSession;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Explains a difference after a blind close, once the counts are frozen. */
class NoteController extends Controller
{
    public function __invoke(Request $request, CashRegisterSession $cashRegisterSession): JsonResponse
    {
        abort_if($cashRegisterSession->user_id !== $request->user()->id, 403);

        if (! $cashRegisterSession->needsNote()) {
            return response()->json(['message' => __('app.pos.cash_session.note_not_needed')], 422);
        }

        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        $cashRegisterSession->update(['notes' => $data['notes']]);

        return response()->json($cashRegisterSession->toSummary());
    }
}
