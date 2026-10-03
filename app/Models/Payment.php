<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['company_id', 'sale_id', 'payment_method_id', 'amount', 'paid_at', 'received_by', 'cash_register_session_id', 'reference', 'notes'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    use SoftDeletes {
        restore as restoreWithoutTransaction;
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sale_id', 'payment_method_id', 'amount', 'paid_at'])
            ->logOnlyDirty();
    }

    protected static function booted(): void
    {
        // The drawer that received the money.
        static::creating(function (Payment $payment): void {
            if ($payment->cash_register_session_id === null && $payment->received_by !== null) {
                $payment->cash_register_session_id = CashRegisterSession::openFor(User::find($payment->received_by))?->id;
            }
        });

        // Store credit mirrors into the customer's ledger: a negative payment grants it, a positive one spends it.
        static::creating(function (Payment $payment): void {
            if (! $payment->isStoreCredit()) {
                return;
            }

            $customerId = Sale::withoutGlobalScopes()->whereKey($payment->sale_id)->value('customer_id');
            if ($customerId === null) {
                throw ValidationException::withMessages(['payments' => __('app.store_credit.needs_customer')]);
            }

            if ($payment->amount > 0) {
                $payment->assertCreditCovers($customerId, -$payment->amount, 'app.store_credit.not_enough');
            }
        });
        static::created(fn (Payment $payment) => $payment->mirrorStoreCredit(-$payment->amount));

        // A store-credit payment is immutable: the ledger entry was written for its original amount, method and sale.
        // Corrections are a delete plus a new payment.
        static::updating(function (Payment $payment): void {
            if (! $payment->isDirty(['amount', 'payment_method_id', 'sale_id'])) {
                return;
            }

            $wasStoreCredit = (bool) PaymentMethod::withoutGlobalScopes()->whereKey($payment->getOriginal('payment_method_id'))->value('is_store_credit');
            if ($wasStoreCredit || $payment->isStoreCredit()) {
                throw ValidationException::withMessages(['payments' => __('app.store_credit.immutable')]);
            }
        });

        // Deleting a grant must not leave the customer with negative credit; restoring a spend must still be covered.
        static::deleting(function (Payment $payment): void {
            $delta = $payment->ledgerDelta(active: false);
            if ($delta < 0) {
                $payment->assertCreditCovers($payment->ledgerCustomerId(), $delta, 'app.store_credit.would_go_negative');
            }
        });
        static::deleted(fn (Payment $payment) => $payment->syncStoreCredit(active: false));
        static::restoring(function (Payment $payment): void {
            $delta = $payment->ledgerDelta(active: true);
            if ($delta < 0) {
                $payment->assertCreditCovers($payment->ledgerCustomerId(), $delta, 'app.store_credit.would_go_negative');
            }
        });
        static::restored(fn (Payment $payment) => $payment->syncStoreCredit(active: true));

        static::saved(fn (Payment $payment) => $payment->sale?->recalculateStatus());
        static::deleted(fn (Payment $payment) => $payment->sale?->recalculateStatus());
    }

    public function isStoreCredit(): bool
    {
        return (bool) PaymentMethod::withoutGlobalScopes()->whereKey($this->payment_method_id)->value('is_store_credit');
    }

    /**
     * The store-credit lock only holds inside a transaction, so every write of a payment runs in one:
     * callers (POS, Filament actions, future returns) cannot forget it.
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    public function restore(): bool
    {
        return DB::transaction(fn (): bool => $this->restoreWithoutTransaction());
    }

    /** Throw unless the customer's credit, locked for the check, stays at or above zero after $delta. */
    private function assertCreditCovers(int $customerId, int $delta, string $message): void
    {
        // Lock the customer so two sales can't spend the same credit.
        $customer = Customer::withoutGlobalScopes()->whereKey($customerId)->lockForUpdate()->firstOrFail();
        $balance = $customer->creditBalance();

        if ($balance + $delta < 0) {
            throw ValidationException::withMessages(['payments' => __($message, ['balance' => $balance])]);
        }
    }

    /** @return Builder<CustomerCredit> This payment's own ledger entries. */
    private function ledgerEntries(): Builder
    {
        return CustomerCredit::withoutGlobalScopes()
            ->where('source_type', $this->getMorphClass())
            ->where('source_id', $this->id);
    }

    /** The customer of the original ledger entry — the sale's customer may have changed since. */
    private function ledgerCustomerId(): int
    {
        return (int) $this->ledgerEntries()->orderBy('id')->value('customer_id');
    }

    /** Ledger change needed to bring this payment's entries in line with it being active or not (0 when already so). */
    private function ledgerDelta(bool $active): int
    {
        if (! $this->ledgerEntries()->exists()) {
            return 0;
        }

        $net = (int) $this->ledgerEntries()->sum('amount');

        return $active ? ($net === 0 ? -$this->amount : 0) : -$net;
    }

    /** Write the customer-credit ledger entry for a store-credit payment (no-op for any other method). */
    public function mirrorStoreCredit(int $amount): void
    {
        if (! $this->isStoreCredit()) {
            return;
        }

        CustomerCredit::create([
            'company_id' => $this->company_id,
            'customer_id' => Sale::withoutGlobalScopes()->whereKey($this->sale_id)->value('customer_id'),
            'amount' => $amount,
            'source_type' => $this->getMorphClass(),
            'source_id' => $this->id,
        ]);
    }

    /**
     * Delete / restore bookkeeping, idempotent: the entries of this payment are netted, so a force-delete of
     * an already trashed payment or a restore of an active one writes nothing.
     */
    private function syncStoreCredit(bool $active): void
    {
        $delta = $this->ledgerDelta($active);
        if ($delta === 0) {
            return;
        }

        CustomerCredit::create([
            'company_id' => $this->company_id,
            'customer_id' => $this->ledgerCustomerId(),
            'amount' => $delta,
            'source_type' => $this->getMorphClass(),
            'source_id' => $this->id,
        ]);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
