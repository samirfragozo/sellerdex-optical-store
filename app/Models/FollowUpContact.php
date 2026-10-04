<?php

namespace App\Models;

use App\Enums\FollowUpReason;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A customer the shop already reached for a follow-up reason; it hides them from that list. */
#[Fillable(['company_id', 'customer_id', 'reason', 'subject_type', 'subject_id', 'user_id', 'contacted_at'])]
class FollowUpContact extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['reason' => FollowUpReason::class, 'contacted_at' => 'datetime'];
    }

    public static function record(Customer $customer, FollowUpReason $reason, ?Model $subject): self
    {
        return self::create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'reason' => $reason,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'user_id' => auth()->id(),
            'contacted_at' => now(),
        ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
