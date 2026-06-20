<?php

namespace App\Models;

use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'is_active', 'is_default', 'sort_order'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    /**
     * The default method (Cash) can NEVER be deleted, not even by the super admin
     * (who bypasses policies). This is enforced at the model level.
     */
    protected static function booted(): void
    {
        static::deleting(function (PaymentMethod $method): bool {
            return ! $method->is_default;
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** The default method (Cash) cannot be deleted nor deactivated. */
    public function isProtected(): bool
    {
        return $this->is_default;
    }
}
