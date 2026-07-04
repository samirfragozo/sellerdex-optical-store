<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'purchase_order_id', 'product_id', 'quantity', 'unit_cost', 'subtotal'])]
class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item): void {
            $item->subtotal = (int) $item->quantity * (int) $item->unit_cost;
        });

        static::saved(fn (PurchaseOrderItem $item) => $item->purchaseOrder?->recalculateTotal());
        static::deleted(fn (PurchaseOrderItem $item) => $item->purchaseOrder?->recalculateTotal());
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
