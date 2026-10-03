<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        static::saved(fn (Payment $payment) => $payment->sale?->recalculateStatus());
        static::deleted(fn (Payment $payment) => $payment->sale?->recalculateStatus());
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
