<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['company_id', 'sale_id', 'group_key', 'product_id', 'description', 'quantity', 'unit_price', 'unit_cost', 'tax_name', 'tax_rate', 'tax_treatment', 'tax_amount', 'line_total'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'line_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SaleItem $item): void {
            $item->line_total = $item->quantity * $item->unit_price;

            $rate = (float) $item->tax_rate;
            // Prices are tax-inclusive: the VAT is the part of the line total above its base.
            $item->tax_amount = $rate > 0
                ? (int) round($item->line_total - $item->line_total / (1 + $rate / 100))
                : 0;
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

    /**
     * Snapshot of the tax that applies to a new line (null = untaxed).
     *
     * @return array{tax_name: string|null, tax_rate: float|int, tax_treatment: string|null}
     */
    public static function taxSnapshot(?Tax $tax): array
    {
        return [
            'tax_name' => $tax?->name,
            'tax_rate' => $tax !== null ? (float) $tax->rate : 0,
            'tax_treatment' => $tax?->treatment->value,
        ];
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

    public function lensOrder(): HasOne
    {
        return $this->hasOne(LensOrder::class);
    }

    public function lensConfig(): HasOne
    {
        return $this->hasOne(SaleItemLensConfig::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(SaleItemOption::class);
    }

    /** True when this line is a made-to-order lens (carries a resolved lens configuration). */
    public function isLens(): bool
    {
        return $this->relationLoaded('lensConfig')
            ? $this->lensConfig !== null
            : $this->lensConfig()->exists();
    }
}
