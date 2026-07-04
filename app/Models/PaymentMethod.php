<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'is_active', 'is_default', 'sort_order', 'surcharge_percent'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * The default method (Cash) can NEVER be deleted, and a method with payments
     * cannot be deleted either — enforced at the model level (super admin bypasses policies).
     */
    protected static function booted(): void
    {
        static::deleting(function (PaymentMethod $method): bool {
            return ! $method->is_default && ! $method->hasChildren();
        });
    }

    public function hasChildren(): bool
    {
        return Payment::withTrashed()->where('payment_method_id', $this->id)->exists();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'surcharge_percent' => 'decimal:2',
        ];
    }

    /** The default method (Cash) cannot be deleted nor deactivated. */
    public function isProtected(): bool
    {
        return $this->is_default;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
