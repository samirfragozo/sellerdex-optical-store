<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['company_id', 'sale_id', 'payment_method_id', 'amount', 'paid_at', 'received_by', 'cash_register_session_id', 'reference', 'notes'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

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
                // Lock the customer so two sales can't spend the same credit.
                $customer = Customer::withoutGlobalScopes()->whereKey($customerId)->lockForUpdate()->firstOrFail();
                if ($payment->amount > $customer->creditBalance()) {
                    throw ValidationException::withMessages(['payments' => __('app.store_credit.not_enough', ['balance' => $customer->creditBalance()])]);
                }
            }
        });
        static::created(fn (Payment $payment) => $payment->mirrorStoreCredit(-$payment->amount));
        static::deleted(fn (Payment $payment) => $payment->mirrorStoreCredit($payment->amount));

        static::saved(fn (Payment $payment) => $payment->sale?->recalculateStatus());
        static::deleted(fn (Payment $payment) => $payment->sale?->recalculateStatus());
    }

    private function isStoreCredit(): bool
    {
        return (bool) PaymentMethod::withoutGlobalScopes()->whereKey($this->payment_method_id)->value('is_store_credit');
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
