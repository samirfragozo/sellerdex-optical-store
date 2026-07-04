<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Traits\BelongsToCompany;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable(['company_id', 'number', 'supplier_id', 'status', 'ordered_at', 'received_at', 'total', 'notes', 'created_by'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'ordered_at' => 'date',
            'received_at' => 'date',
            'total' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** Recompute the order total from its item subtotals. */
    public function recalculateTotal(): void
    {
        $this->update(['total' => (int) $this->items()->sum('subtotal')]);
    }

    /** Mark the order received and replenish stock for stockable items (idempotent). */
    public function receive(): void
    {
        DB::transaction(function (): void {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->status === PurchaseOrderStatus::Received) {
                return;
            }

            $this->loadMissing('items.product');
            foreach ($this->items as $item) {
                if ($item->product?->is_stockable === true) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $this->update(['status' => PurchaseOrderStatus::Received, 'received_at' => now()]);
        });
    }

    /** Cancel the order; if it was received, roll the replenished stock back. */
    public function cancel(): void
    {
        DB::transaction(function (): void {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->status === PurchaseOrderStatus::Cancelled) {
                return;
            }

            if ($locked->status === PurchaseOrderStatus::Received) {
                $this->loadMissing('items.product');
                foreach ($this->items as $item) {
                    if ($item->product?->is_stockable === true) {
                        $item->product->decrement('stock', $item->quantity);
                    }
                }
            }

            $this->update(['status' => PurchaseOrderStatus::Cancelled]);
        });
    }
}
