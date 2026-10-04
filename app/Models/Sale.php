<?php

namespace App\Models;

use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Enums\LayawayInvoicing;
use App\Enums\LensOrderStatus;
use App\Enums\RemakeResponsible;
use App\Enums\SaleDocumentType;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\PendingLensOrderException;
use App\Support\StockLedger;
use App\Traits\BelongsToCompany;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable(['company_id', 'number', 'customer_id', 'seller_id', 'document_type', 'status', 'subtotal', 'discount', 'discount_percent', 'surcharge_percent', 'tax_amount', 'total', 'is_delivered', 'delivered_at', 'sold_at', 'notes', 'created_by', 'discount_approved_by', 'quote_valid_until'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** SQL for a sale's net value (total minus returns and value adjustments); sum it over non-voided sales for reports. */
    public const NET_VALUE_SQL = "sales.total - (select coalesce(sum(sale_returns.total), 0) from sale_returns where sale_returns.sale_id = sales.id and sale_returns.type <> 'void')";

    protected function casts(): array
    {
        return [
            'document_type' => SaleDocumentType::class,
            'status' => SaleStatus::class,
            'subtotal' => 'integer',
            'discount' => 'integer',
            'discount_percent' => 'decimal:2',
            'surcharge_percent' => 'decimal:2',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'is_delivered' => 'boolean',
            'delivered_at' => 'date',
            'sold_at' => 'date',
            'quote_valid_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Money and returns are accounting records: the DB cascades would silently wipe refunds and store credit.
        static::forceDeleting(fn (Sale $sale): bool => ! $sale->payments()->withTrashed()->exists() && ! $sale->returns()->exists() && ! $sale->fiscalDocuments()->exists());

        static::created(fn (Sale $sale) => $sale->issueReceiptIfDue());

        static::creating(function (Sale $sale): void {
            $sale->number ??= $sale->company_id !== null
                ? Company::takeNextSaleNumber($sale->company_id)
                // ponytail: company-less sales only happen in unauthenticated test fixtures
                : str_pad((string) (static::withoutGlobalScopes()->withTrashed()->whereNull('company_id')->count() + 1), 6, '0', STR_PAD_LEFT);
            $sale->sold_at ??= now()->toDateString();

            if ($sale->document_type === SaleDocumentType::Quote || $sale->document_type === SaleDocumentType::Quote->value) {
                $days = (int) (Company::withoutGlobalScopes()->whereKey($sale->company_id)->value('quote_validity_days') ?? 15);
                $sale->quote_valid_until ??= Carbon::parse($sale->sold_at)->addDays($days)->toDateString();
            }
        });

        static::updating(function (Sale $sale): void {
            $deliveringNow = $sale->isDirty('is_delivered')
                && $sale->is_delivered === true
                && (bool) $sale->getOriginal('is_delivered') === false;

            if ($deliveringNow && $sale->hasPendingLensWork()) {
                throw new PendingLensOrderException(__('app.sale_actions.cannot_deliver_pending_lens'));
            }
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

            $sale->issueReceiptIfDue();
        });
    }

    /** A quote past its validity date: converting it re-prices it. */
    public function isExpiredQuote(): bool
    {
        return $this->document_type === SaleDocumentType::Quote
            && $this->quote_valid_until !== null
            && $this->quote_valid_until->lt(today());
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

    /** Only the units still sold move: returned units were already restocked or written off by their return. */
    private function adjustStock(bool $decrement): void
    {
        foreach ($this->items()->with('product')->get() as $item) {
            $quantity = $item->returnableQuantity();
            if ($item->product !== null && $quantity > 0) {
                StockLedger::record($item->product, StockMovementType::Sale, $decrement ? -$quantity : $quantity, $this);
            }
        }
    }

    /**
     * Sales with an outstanding balance (net value greater than the sum of payments).
     * Uses correlated subqueries — SQLite rejects HAVING without GROUP BY.
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->where('sales.status', '!=', SaleStatus::Voided->value)->whereRaw("sales.total - (select coalesce(sum(sale_returns.total), 0) from sale_returns where sale_returns.sale_id = sales.id and sale_returns.type <> 'void') > (select coalesce(sum(payments.amount), 0) from payments where payments.sale_id = sales.id and payments.deleted_at is null)");
    }

    /** Sales that still count in reports: everything but voided ones. */
    public function scopeNotVoided(Builder $query): void
    {
        $query->where('sales.status', '!=', SaleStatus::Voided->value);
    }

    /** What the customer was given back in value: returns and value adjustments (a void cancels the sale instead). */
    public function returnedTotal(): int
    {
        return (int) $this->returns()
            ->whereIn('type', [SaleReturnType::Return->value, SaleReturnType::ValueAdjustment->value])
            ->sum('total');
    }

    /** The share of a line's value actually charged after the sale discount (the stored discount, not the editable percent). */
    public function chargedFactor(): float
    {
        return $this->subtotal > 0 ? 1 - $this->discount / $this->subtotal : 1;
    }

    /** Returns, value adjustments and voids freeze the sale's value: totals, discount and lines can no longer change. */
    public function isLockedForEdits(): bool
    {
        return $this->status === SaleStatus::Voided || $this->returns()->exists();
    }

    /** The sale's value once returns are taken out. */
    public function netTotal(): int
    {
        return $this->total - $this->returnedTotal();
    }

    public function totalPaid(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    /** What a layaway keeps out of what was paid when cancelled (the sale's company fee percent); 0 for any other sale. */
    public function cancellationFee(): int
    {
        if ($this->document_type !== SaleDocumentType::Layaway) {
            return 0;
        }

        $percent = (float) Company::withoutGlobalScopes()->whereKey($this->company_id)->value('layaway_cancellation_fee_percent');

        return (int) round(max(0, $this->totalPaid()) * $percent / 100);
    }

    /** Outstanding balance (net value minus payments); a voided sale owes nothing. */
    protected function balance(): Attribute
    {
        return Attribute::get(fn (): int => $this->status === SaleStatus::Voided
            ? 0
            : max(0, $this->netTotal() - $this->totalPaid()));
    }

    public function recalculateTotals(): void
    {
        $subtotal = (int) $this->items()->sum('line_total');
        $this->subtotal = $subtotal;

        $this->discount = (int) round($subtotal * ((float) $this->discount_percent) / 100);
        $base = max(0, $subtotal - $this->discount);

        // Prices are tax-inclusive: tax_amount is informational, never added on top.
        $this->total = (int) round($base * (1 + ((float) $this->surcharge_percent) / 100));

        // The payment-method surcharge applies on top of the tax-inclusive price and
        // carries no VAT, so the VAT is prorated only by the discount.
        $rawTax = (int) $this->items()->sum('tax_amount');
        $this->tax_amount = $subtotal > 0
            ? (int) round($rawTax * $base / $subtotal)
            : 0;

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
        $netTotal = $this->netTotal();

        $status = match (true) {
            $this->is_delivered => SaleStatus::Delivered,
            $this->total > 0 && $paid >= $netTotal => SaleStatus::Paid,
            $paid > 0 => SaleStatus::Partial,
            default => SaleStatus::Draft,
        };

        if ($status !== $this->status) {
            $this->status = $status;
            $this->saveQuietly();
        }
    }

    /** Sale items that carry a lens configuration (they generate a lab order). */
    public function lensItems(): Collection
    {
        return $this->items()->with(['lensConfig', 'lensOrder'])->get()
            ->filter(fn (SaleItem $item): bool => $item->isLens());
    }

    /**
     * True if any lens item lacks a ready lab order (missing order counts as pending).
     * A cancelled order or a fully returned item has no work left.
     */
    public function hasPendingLensWork(): bool
    {
        return $this->lensItems()->contains(
            fn (SaleItem $item): bool => ! in_array($item->lensOrder?->lab_status, [LensOrderStatus::Ready, LensOrderStatus::Cancelled], true)
                && $item->returnableQuantity() > 0
        );
    }

    public function canBeDelivered(): bool
    {
        return ! $this->hasPendingLensWork();
    }

    /**
     * What the sale really earns: revenue without taxes (the payment surcharge
     * is left out — it pays the platform's fee) minus the cost of what was
     * sold and of every remake the store was responsible for.
     */
    public function realMargin(): int
    {
        $revenue = $this->subtotal - $this->discount - $this->tax_amount;
        $cost = (int) $this->items()->sum(DB::raw('unit_cost * quantity'));
        $storeRemakes = (int) LensOrder::query()
            ->whereIn('sale_item_id', $this->items()->select('id'))
            ->where('remake_responsible', RemakeResponsible::Store->value)
            ->sum('remake_cost');

        return $revenue - $cost - $storeRemakes;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function discountApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** The lens configuration of every armado in this sale. */
    public function lensConfigs(): HasManyThrough
    {
        return $this->hasManyThrough(SaleItemLensConfig::class, SaleItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    /** The factura or POS electrónico registered for the sale itself (not for one of its returns). */
    public function saleDocument(): ?FiscalDocument
    {
        return $this->fiscalDocuments()->whereNull('sale_return_id')
            ->whereIn('document_type', [FiscalDocumentType::PosElectronic->value, FiscalDocumentType::ElectronicInvoice->value])
            ->first();
    }

    /**
     * Whether the sale counts as invoiced now: not a quote and not voided; a layaway only once delivered,
     * unless its company invoices layaways at sale time.
     */
    public function isInvoiceableNow(): bool
    {
        if ($this->company_id === null || $this->status === SaleStatus::Voided || $this->document_type === SaleDocumentType::Quote) {
            return false;
        }

        if ($this->document_type !== SaleDocumentType::Layaway) {
            return true;
        }

        return $this->is_delivered
            || Company::withoutGlobalScopes()->whereKey($this->company_id)->value('layaway_invoicing') === LayawayInvoicing::OnSale;
    }

    public function receipt(): ?FiscalDocument
    {
        return $this->fiscalDocuments()->where('document_type', FiscalDocumentType::Receipt->value)->first();
    }

    /** Receipt-only companies number every invoiced sale once; a voided sale keeps its number. */
    public function issueReceiptIfDue(): void
    {
        $mode = Company::withoutGlobalScopes()->whereKey($this->company_id)->value('invoicing_mode');

        if ($mode !== InvoicingMode::ReceiptOnly || ! $this->isInvoiceableNow() || $this->receipt() !== null) {
            return;
        }

        FiscalDocument::create([
            'company_id' => $this->company_id,
            'sale_id' => $this->id,
            'document_type' => FiscalDocumentType::Receipt,
            'source' => FiscalDocumentSource::Internal,
            'number' => NumberingRange::takeNext($this->company_id, FiscalDocumentType::Receipt),
            'issued_at' => today(),
            'status' => FiscalDocumentStatus::NotApplicable,
            'registered_by' => auth()->id(),
        ]);
    }
}
