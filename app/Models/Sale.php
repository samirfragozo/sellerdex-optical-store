<?php

namespace App\Models;

use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['number', 'customer_id', 'seller_id', 'prescription_id', 'document_type', 'status', 'subtotal', 'discount', 'surcharge_percent', 'total', 'is_delivered', 'delivered_at', 'sold_at', 'notes', 'created_by'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'document_type' => SaleDocumentType::class,
            'status' => SaleStatus::class,
            'subtotal' => 'integer',
            'discount' => 'integer',
            'surcharge_percent' => 'decimal:2',
            'total' => 'integer',
            'is_delivered' => 'boolean',
            'delivered_at' => 'date',
            'sold_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sale $sale): void {
            $sale->number ??= self::nextNumber();
            $sale->sold_at ??= now()->toDateString();
        });

        // React whenever a change flips whether the sale should be holding stock
        // (void/unvoid, deliver a layaway, or convert a quote into an order).
        static::updated(function (Sale $sale): void {
            $heldBefore = self::stockHeldFor(
                SaleDocumentType::from($sale->getRawOriginal('document_type')),
                SaleStatus::from($sale->getRawOriginal('status')),
                (bool) $sale->getRawOriginal('is_delivered'),
            );
            $holdsNow = $sale->holdsStock();

            if ($holdsNow && ! $heldBefore) {
                $sale->deductStock();
            } elseif (! $holdsNow && $heldBefore) {
                $sale->restoreStock();
            }

            // Keep the status in sync with the delivery flag.
            if ($sale->wasChanged('is_delivered')) {
                $sale->recalculateStatus();
            }
        });
    }

    /**
     * Whether this sale should currently have its items deducted from stock.
     * Quotes never hold stock; a layaway only holds it once delivered.
     */
    public function holdsStock(): bool
    {
        return self::stockHeldFor($this->document_type, $this->status, (bool) $this->is_delivered);
    }

    private static function stockHeldFor(SaleDocumentType $type, SaleStatus $status, bool $delivered): bool
    {
        if ($status === SaleStatus::Voided) {
            return false;
        }

        return match ($type) {
            SaleDocumentType::Quote => false,
            SaleDocumentType::Layaway => $delivered,
            default => true,
        };
    }

    public function deductStock(): void
    {
        $this->adjustStock(decrement: true);
    }

    public function restoreStock(): void
    {
        $this->adjustStock(decrement: false);
    }

    private function adjustStock(bool $decrement): void
    {
        foreach ($this->items()->with('product')->get() as $item) {
            if ($item->product?->is_stockable) {
                $decrement
                    ? $item->product->decrement('stock', $item->quantity)
                    : $item->product->increment('stock', $item->quantity);
            }
        }
    }

    /**
     * Sales with an outstanding balance (total greater than the sum of payments).
     * Uses a correlated subquery — SQLite rejects HAVING without GROUP BY.
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereRaw('sales.total > (select coalesce(sum(payments.amount), 0) from payments where payments.sale_id = sales.id and payments.deleted_at is null)');
    }

    /** Next sequential sale number, zero-padded (e.g. 000001). */
    public static function nextNumber(): string
    {
        $max = (int) static::withTrashed()->max('id');

        return str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    public function totalPaid(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    /** Outstanding balance (total minus payments). */
    protected function balance(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->total - $this->totalPaid()));
    }

    public function recalculateTotals(): void
    {
        $subtotal = (int) $this->items()->sum('line_total');
        $this->subtotal = $subtotal;
        $base = max(0, $subtotal - $this->discount);
        $this->total = (int) round($base * (1 + ((float) $this->surcharge_percent) / 100));
        $this->saveQuietly();
        $this->recalculateStatus();
    }

    /** Move status along the draft -> partial -> paid track (unless voided/delivered). */
    public function recalculateStatus(): void
    {
        if ($this->status === SaleStatus::Voided) {
            return;
        }

        $paid = $this->totalPaid();

        $status = match (true) {
            $this->is_delivered => SaleStatus::Delivered,
            $this->total > 0 && $paid >= $this->total => SaleStatus::Paid,
            $paid > 0 => SaleStatus::Partial,
            default => SaleStatus::Draft,
        };

        if ($status !== $this->status) {
            $this->status = $status;
            $this->saveQuietly();
        }
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
