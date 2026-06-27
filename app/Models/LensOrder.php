<?php

namespace App\Models;

use App\Enums\LensOrderStatus;
use Database\Factories\LensOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_item_id', 'supplier_id', 'lab_status', 'expected_date', 'received_date', 'notes'])]
class LensOrder extends Model
{
    /** @use HasFactory<LensOrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'lab_status' => LensOrderStatus::class,
            'expected_date' => 'date',
            'received_date' => 'date',
        ];
    }

    /** @param  Builder<LensOrder>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('lab_status', '!=', LensOrderStatus::Received->value);
    }

    public function isReceived(): bool
    {
        return $this->lab_status === LensOrderStatus::Received;
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
