<?php

namespace App\Actions;

use App\Enums\CashMovementType;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The arqueo: count every method, keep tomorrow's float, withdraw the rest for deposit. */
class CloseCashRegisterSession
{
    /** @param  array<int|string, int>  $counts  payment method id => counted amount */
    public function handle(CashRegisterSession $session, array $counts, int $cashLeft, ?string $notes, User $closedBy): CashRegisterSession
    {
        return DB::transaction(function () use ($session, $counts, $cashLeft, $notes, $closedBy): CashRegisterSession {
            $locked = CashRegisterSession::withoutGlobalScopes()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->closed_at !== null) {
                throw ValidationException::withMessages(['session' => __('app.pos.cash_session.already_closed')]);
            }

            $cashMethodId = $locked->cashMethodId();
            $expected = $locked->expectedByMethod();
            $methodIds = collect(array_keys($expected))->merge(array_map('intval', array_keys($counts)))->unique()->sort()->values();
            $counted = $methodIds->mapWithKeys(fn (int $id): array => [$id => (int) ($counts[$id] ?? 0)]);
            $countedCash = (int) ($counted[$cashMethodId] ?? 0);

            if ($cashLeft < 0 || $cashLeft > $countedCash) {
                throw ValidationException::withMessages(['cash_left' => __('app.pos.cash_session.cash_left_too_high')]);
            }

            // The session's shop, not the closer's: an admin may close another shop's drawer.
            $company = Company::withoutGlobalScopes()->findOrFail($locked->company_id);
            $threshold = (int) $company->cash_difference_note_threshold;
            $overThreshold = $methodIds->contains(fn (int $id): bool => abs($counted[$id] - ($expected[$id] ?? 0)) > $threshold);

            // Blind: never reject on the note before the counts are frozen, or retries would reveal the expected
            // amounts. The missing note is asked for afterwards (see CashRegisterSession::needsNote()).
            // Whoever isn't the cashier already sees the expected amounts, so blind mode doesn't apply to them.
            $isCloserTheCashier = $closedBy->id === $locked->user_id;
            if ($overThreshold && blank($notes) && (! $company->blind_cash_count || ! $isCloserTheCashier)) {
                throw ValidationException::withMessages(['notes' => __('app.pos.cash_session.notes_required')]);
            }

            foreach ($methodIds as $id) {
                $locked->counts()->create([
                    'company_id' => $locked->company_id,
                    'payment_method_id' => $id,
                    'expected' => $expected[$id] ?? 0,
                    'counted' => $counted[$id],
                    'difference' => $counted[$id] - ($expected[$id] ?? 0),
                ]);
            }

            $locked->update([
                'closed_at' => now(),
                'closed_cash' => $countedCash,
                'expected_cash' => $expected[$cashMethodId] ?? $locked->opening_cash,
                'cash_left' => $cashLeft,
                'closed_by' => $closedBy->id,
                'closed_by_admin' => ! $isCloserTheCashier,
                'notes' => $notes,
            ]);

            // Created after the count, so it never changes what was expected.
            if ($countedCash - $cashLeft > 0) {
                $locked->movements()->create([
                    'company_id' => $locked->company_id,
                    'type' => CashMovementType::Withdrawal,
                    'amount' => $countedCash - $cashLeft,
                    'reason' => __('app.pos.cash_session.deposit'),
                    'user_id' => $closedBy->id,
                ]);
            }

            return $locked->fresh()->load(['counts' => fn ($query) => $query
                ->withoutGlobalScopes()
                ->with(['paymentMethod' => fn ($query) => $query->withoutGlobalScopes()])]);
        });
    }
}
