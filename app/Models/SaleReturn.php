<?php

namespace App\Models;

use App\Enums\MoneyDestination;
use App\Enums\SaleReturnType;
use App\Traits\BelongsToCompany;
use Database\Factories\SaleReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'sale_id', 'type', 'reason', 'money_destination', 'total', 'refund_amount', 'refund_payment_method_id', 'store_credit_amount', 'retained_amount', 'loss_amount', 'user_id', 'approved_by'])]
class SaleReturn extends Model
{
    /** @use HasFactory<SaleReturnFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => SaleReturnType::class,
            'money_destination' => MoneyDestination::class,
            'total' => 'integer',
            'refund_amount' => 'integer',
            'store_credit_amount' => 'integer',
            'retained_amount' => 'integer',
            'loss_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // A return changes what the sale is worth, so its status follows.
        static::saved(fn (SaleReturn $return) => $return->sale?->recalculateStatus());
        static::deleted(fn (SaleReturn $return) => $return->sale?->recalculateStatus());
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function refundPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'refund_payment_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }
}
