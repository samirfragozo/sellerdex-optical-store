<?php

namespace App\Models;

use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyClaimType;
use App\Enums\WarrantyResolution;
use App\Enums\WarrantyResponsible;
use App\Traits\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\WarrantyClaimFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'sale_item_id', 'type', 'customer_description', 'received_at', 'status', 'resolution', 'rejection_reason', 'responsible', 'store_cost', 'replacement_product_id', 'lens_order_id', 'sale_return_id', 'user_id', 'resolved_by', 'resolved_at', 'delivered_at'])]
class WarrantyClaim extends Model
{
    /** @use HasFactory<WarrantyClaimFactory> */
    use BelongsToCompany, HasFactory;

    /** The plain moves; resolving goes through ResolveWarrantyClaim. */
    private const STEPS = [
        'received' => ['in_review'],
        'in_review' => ['at_supplier'],
        'resolved' => ['delivered'],
    ];

    protected function casts(): array
    {
        return [
            'type' => WarrantyClaimType::class,
            'status' => WarrantyClaimStatus::class,
            'resolution' => WarrantyResolution::class,
            'responsible' => WarrantyResponsible::class,
            'store_cost' => 'integer',
            'received_at' => 'date',
            'resolved_at' => 'datetime',
            'delivered_at' => 'date',
        ];
    }

    /**
     * Last day a claim of this type is accepted for the line, or null when the sale is not delivered.
     * The legal term runs from delivery and stops while the line is under claim (Ley 1480).
     */
    public static function deadlineFor(SaleItem $item, WarrantyClaimType $type): ?CarbonInterface
    {
        $deliveredAt = $item->sale?->delivered_at;
        if (! $item->sale?->is_delivered || $deliveredAt === null) {
            return null;
        }

        if ($type === WarrantyClaimType::Adaptation) {
            $days = (int) Company::withoutGlobalScopes()->whereKey($item->sale->company_id)->value('adaptation_warranty_days');

            return $deliveredAt->copy()->addDays($days);
        }

        $suspended = (int) self::withoutGlobalScopes()->where('sale_item_id', $item->id)->get()
            ->sum(fn (self $claim): int => (int) $claim->received_at->diffInDays($claim->delivered_at ?? today()));

        return $deliveredAt->copy()->addMonthsNoOverflow($item->warrantyMonths())->addDays($suspended);
    }

    public function advance(WarrantyClaimStatus $to): void
    {
        if (! in_array($to->value, self::STEPS[$this->status->value] ?? [], true)) {
            throw new DomainException(__('app.warranty.invalid_transition'));
        }

        $this->update(['status' => $to, 'delivered_at' => $to === WarrantyClaimStatus::Delivered ? today() : $this->delivered_at]);
    }

    public function isOpen(): bool
    {
        return $this->status !== WarrantyClaimStatus::Delivered;
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function lensOrder(): BelongsTo
    {
        return $this->belongsTo(LensOrder::class);
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function replacementProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'replacement_product_id');
    }
}
