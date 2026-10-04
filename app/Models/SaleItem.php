<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Enums\TaxTreatment;
use App\Enums\VatRegime;
use App\Support\StockLedger;
use App\Traits\BelongsToCompany;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['company_id', 'sale_id', 'group_key', 'product_id', 'description', 'quantity', 'unit_price', 'unit_cost', 'tax_name', 'tax_rate', 'tax_treatment', 'line_total', 'warranty_months'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use BelongsToCompany, HasFactory;

    /** Ley 1480: with no stated term, one year is presumed. */
    public const LEGAL_WARRANTY_MONTHS = 12;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_treatment' => TaxTreatment::class,
            'tax_amount' => 'integer',
            'line_total' => 'integer',
            'warranty_months' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The term printed on the sale document: a later category edit never changes it.
        static::creating(function (SaleItem $item): void {
            $item->warranty_months ??= ($item->product?->category ?? $item->product?->baseProduct?->category)?->warranty_months;
        });

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
                StockLedger::record($item->product, StockMovementType::Sale, -$item->quantity, $item);
            }
        });

        static::updated(function (SaleItem $item): void {
            if (! $item->wasChanged('quantity') || ! $item->movesStock()) {
                return;
            }
            $delta = (int) $item->quantity - (int) $item->getOriginal('quantity');
            if ($delta !== 0) {
                StockLedger::record($item->product, StockMovementType::Sale, -$delta, $item);
            }
        });

        static::deleted(function (SaleItem $item): void {
            if ($item->movesStock()) {
                StockLedger::record($item->product, StockMovementType::Sale, $item->quantity, $item);
            }
        });
    }

    /**
     * Snapshot of a product's tax for a new line: a variant without its own tax
     * follows its base product.
     *
     * @return array{tax_name: string|null, tax_rate: float|int, tax_treatment: string|null}
     */
    public static function taxSnapshotFor(?Product $product, Company $company): array
    {
        return self::taxSnapshot($product?->tax ?? $product?->baseProduct?->tax, $company);
    }

    /**
     * Snapshot of the tax that applies to a new line (null = untaxed). A company
     * that is not VAT responsible never taxes a line.
     *
     * @return array{tax_name: string|null, tax_rate: float|int, tax_treatment: string|null}
     */
    public static function taxSnapshot(?Tax $tax, Company $company): array
    {
        if ($company->vat_regime !== VatRegime::Responsible) {
            $tax = null;
        }

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

    public function warrantyMonths(): int
    {
        return $this->warranty_months ?? self::LEGAL_WARRANTY_MONTHS;
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The item's current lab order — the latest, so a remake replaces the original. */
    public function lensOrder(): HasOne
    {
        return $this->hasOne(LensOrder::class)->latestOfMany();
    }

    public function lensConfig(): HasOne
    {
        return $this->hasOne(SaleItemLensConfig::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(SaleItemOption::class);
    }

    /** Units of this line already given back. */
    public function returnedQuantity(): int
    {
        return (int) SaleReturnItem::query()->where('sale_item_id', $this->id)->sum('quantity');
    }

    public function returnableQuantity(): int
    {
        return $this->quantity - $this->returnedQuantity();
    }

    /** True when this line is a made-to-order lens (carries a resolved lens configuration). */
    public function isLens(): bool
    {
        return $this->relationLoaded('lensConfig')
            ? $this->lensConfig !== null
            : $this->lensConfig()->exists();
    }
}
