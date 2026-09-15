<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\CashRegisterSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'opened_at', 'opening_cash', 'closed_at', 'closed_cash', 'expected_cash', 'difference', 'notes'])]
class CashRegisterSession extends Model
{
    /** @use HasFactory<CashRegisterSessionFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'opening_cash' => 'integer',
            'closed_at' => 'datetime',
            'closed_cash' => 'integer',
            'expected_cash' => 'integer',
            'difference' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the difference in sync once both sides of the count are known,
        // mirroring CashClose's own booted() hook.
        static::saving(function (CashRegisterSession $session): void {
            if ($session->closed_cash !== null && $session->expected_cash !== null) {
                $session->difference = $session->closed_cash - $session->expected_cash;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function openFor(?User $user): ?self
    {
        if ($user === null) {
            return null;
        }

        return static::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        // ponytail: implicit route binding must bypass global scopes so the controller's own
        // abort_if($cashRegisterSession->user_id !== $request->user()->id, 403) ownership check
        // can run and return 403, rather than CompanyScope filtering the record and returning 404 first.
        return $this->withoutGlobalScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /** Cash payments received by this session's cashier since it opened. */
    public function cashCollected(): int
    {
        $cashMethodId = PaymentMethod::where('is_default', true)->value('id');

        return $cashMethodId
            ? (int) Payment::where('received_by', $this->user_id)
                ->where('payment_method_id', $cashMethodId)
                ->where('created_at', '>=', $this->opened_at)
                ->sum('amount')
            : 0;
    }

    public function expectedCash(): int
    {
        return $this->opening_cash + $this->cashCollected();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'opened_at' => $this->opened_at->toIso8601String(),
            'opening_cash' => $this->opening_cash,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_cash' => $this->closed_cash,
            'expected_cash' => $this->expected_cash,
            'difference' => $this->difference,
        ];
    }
}
