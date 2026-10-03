<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Scopes\CompanyScope;
use App\Traits\BelongsToCompany;
use Database\Factories\CashRegisterSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'user_id', 'opened_at', 'opening_cash', 'closed_at', 'closed_cash', 'expected_cash', 'difference', 'notes', 'cash_left', 'closed_by', 'closed_by_admin', 'reviewed_at', 'reviewed_by'])]
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
            'cash_left' => 'integer',
            'closed_by_admin' => 'boolean',
            'reviewed_at' => 'datetime',
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

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function counts(): HasMany
    {
        return $this->hasMany(CashRegisterSessionCount::class);
    }

    /**
     * What should be in the drawer for each payment method: cash is the float
     * plus cash taken, cash in, minus cash out and cash expenses; any other
     * method is just what was taken with it in this session.
     *
     * @return array<int, int> payment method id => expected amount, ordered by id
     */
    public function expectedByMethod(): array
    {
        $cashMethodId = $this->cashMethodId();

        // The session's own rows, whoever is logged in: skip CompanyScope, keep soft deletes.
        $byMethod = $this->payments()
            ->withoutGlobalScope(CompanyScope::class)
            ->selectRaw('payment_method_id, sum(amount) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id')
            ->map(fn ($total): int => (int) $total)
            ->all();

        if ($cashMethodId !== null) {
            $movements = $this->movements()->withoutGlobalScope(CompanyScope::class)->selectRaw('type, sum(amount) as total')->groupBy('type')->pluck('total', 'type');
            $byMethod[$cashMethodId] = $this->opening_cash
                + ($byMethod[$cashMethodId] ?? 0)
                + (int) ($movements[CashMovementType::Income->value] ?? 0)
                - (int) ($movements[CashMovementType::Withdrawal->value] ?? 0)
                - (int) $this->expenses()->withoutGlobalScope(CompanyScope::class)->where('payment_method_id', $cashMethodId)->sum('amount');
        }

        ksort($byMethod);

        return $byMethod;
    }

    public function expectedCash(): int
    {
        $cashMethodId = $this->cashMethodId();

        return $cashMethodId === null ? $this->opening_cash : $this->expectedByMethod()[$cashMethodId];
    }

    /** The shop's cash method, independent of who is logged in. */
    public function cashMethodId(): ?int
    {
        return PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $this->company_id)
            ->where('is_default', true)
            ->value('id');
    }

    /** Still open although its day is over — its cashier must close it before selling again. */
    public function isStale(): bool
    {
        return $this->closed_at === null && $this->opened_at->lt(today());
    }

    /** Closed with a difference above the shop's threshold and no explanation yet (blind closes ask for it afterwards). */
    public function needsNote(): bool
    {
        if ($this->closed_at === null || filled($this->notes)) {
            return false;
        }

        $threshold = (int) Company::withoutGlobalScopes()->whereKey($this->company_id)->value('cash_difference_note_threshold');

        return $this->counts()->withoutGlobalScopes()->get()->contains(fn (CashRegisterSessionCount $count): bool => abs($count->difference) > $threshold);
    }

    /**
     * The per-method arqueo lines as the POS shows them.
     *
     * @return list<array{payment_method_id: int, name: string|null, expected: int, counted: int, difference: int}>
     */
    public function countsSummary(): array
    {
        return $this->counts()->with('paymentMethod')->orderBy('payment_method_id')->get()
            ->map(fn (CashRegisterSessionCount $count): array => [
                'payment_method_id' => $count->payment_method_id,
                'name' => $count->paymentMethod?->name,
                'expected' => $count->expected,
                'counted' => $count->counted,
                'difference' => $count->difference,
            ])->all();
    }

    /** One drawer per shop: tomorrow's float is what the last close left in it. */
    public static function suggestedOpeningCash(Company $company): int
    {
        return (int) static::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('closed_at')->latest('closed_at')->value('cash_left');
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
            'cash_left' => $this->cash_left,
            'closed_by_admin' => $this->closed_by_admin,
            'is_stale' => $this->isStale(),
            'needs_note' => $this->needsNote(),
        ];
    }
}
