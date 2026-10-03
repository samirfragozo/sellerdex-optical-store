<?php

namespace App\Http\Controllers\CashRegisterSession;

use App\Actions\CloseCashRegisterSession;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\CashRegisterSessionCount;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CloseController extends Controller
{
    public function __invoke(Request $request, CashRegisterSession $cashRegisterSession, CloseCashRegisterSession $close): JsonResponse
    {
        abort_if($cashRegisterSession->user_id !== $request->user()->id, 403);

        if ($cashRegisterSession->closed_at !== null) {
            return response()->json([
                'message' => __('app.pos.cash_session.already_closed'),
            ], 422);
        }

        $data = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['integer', 'min:0'],
            'cash_left' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Only the shop's own methods: active ones, or any that already took money this session.
        $allowed = PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $cashRegisterSession->company_id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', array_keys($cashRegisterSession->expectedByMethod())))
            ->pluck('id')
            ->all();

        // Keys must be canonical positive ids ("5abc" or "05" would silently count as another method).
        $canonical = collect($data['counts'])->keys()->every(fn ($key): bool => (string) (int) $key === (string) $key && (int) $key > 0);

        if (! $canonical || array_diff(array_map('intval', array_keys($data['counts'])), $allowed) !== []) {
            throw ValidationException::withMessages(['counts' => __('app.pos.cash_session.invalid_method')]);
        }

        try {
            $session = $close->handle($cashRegisterSession, $data['counts'], $data['cash_left'], $data['notes'] ?? null, $request->user());
        } catch (ValidationException $exception) {
            // A lost race on the lock is the same "already closed" answer as above.
            if (isset($exception->errors()['session'])) {
                return response()->json(['message' => __('app.pos.cash_session.already_closed')], 422);
            }

            throw $exception;
        }

        return response()->json([
            ...$session->toSummary(),
            'requires_note' => $session->needsNote(),
            'counts' => $session->counts->map(fn (CashRegisterSessionCount $count): array => [
                'payment_method_id' => $count->payment_method_id,
                'name' => $count->paymentMethod?->name,
                'expected' => $count->expected,
                'counted' => $count->counted,
                'difference' => $count->difference,
            ])->sortBy('payment_method_id')->values(),
        ]);
    }
}
