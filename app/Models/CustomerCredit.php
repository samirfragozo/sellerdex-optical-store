<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\CustomerCreditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One signed entry of a customer's store-credit ledger: positive grants credit, negative spends it. */
#[Fillable(['company_id', 'customer_id', 'amount', 'source_type', 'source_id'])]
class CustomerCredit extends Model
{
    /** @use HasFactory<CustomerCreditFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
