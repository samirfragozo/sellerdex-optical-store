<?php

namespace App\Models;

use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'product_id', 'description', 'quantity', 'unit_price', 'unit_cost', 'line_total'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SaleItem $item): void {
            $item->line_total = $item->quantity * $item->unit_price;
        });

        static::saved(fn (SaleItem $item) => $item->sale?->recalculateTotals());
        static::deleted(fn (SaleItem $item) => $item->sale?->recalculateTotals());

        static::created(function (SaleItem $item): void {
            if ($item->movesStock()) {
                $item->product->decrement('stock', $item->quantity);
            }
        });

        static::updated(function (SaleItem $item): void {
            if (! $item->wasChanged('quantity') || ! $item->movesStock()) {
                return;
            }
            $delta = (int) $item->quantity - (int) $item->getOriginal('quantity');
            if ($delta > 0) {
                $item->product->decrement('stock', $delta);
            } elseif ($delta < 0) {
                $item->product->increment('stock', -$delta);
            }
        });

        static::deleted(function (SaleItem $item): void {
            if ($item->movesStock()) {
                $item->product->increment('stock', $item->quantity);
            }
        });
    }

    /** True when selling this line should move product stock. */
    public function movesStock(): bool
    {
        return $this->product?->is_stockable === true
            && $this->sale?->holdsStock() === true;
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
