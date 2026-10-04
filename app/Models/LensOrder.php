<?php

namespace App\Models;

use App\Enums\FrameSource;
use App\Enums\FrameType;
use App\Enums\LensKind;
use App\Enums\LensOrderStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Support\WhatsApp;
use App\Traits\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\LensOrderFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'sale_item_id', 'supplier_id', 'lab_status', 'expected_date', 'received_date', 'notes',
    'od_pd', 'os_pd', 'od_height', 'os_height', 'frame_a', 'frame_b', 'frame_dbl',
    'frame_type', 'frame_source', 'customer_frame_description', 'customer_frame_condition',
    'prescription_snapshot', 'sent_at', 'customer_notified_at',
    'remake_of_id', 'remake_reason', 'remake_responsible', 'remake_cost',
])]
class LensOrder extends Model
{
    /** @use HasFactory<LensOrderFactory> */
    use BelongsToCompany, HasFactory;

    /** A new order always starts pending; it moves on only through the workflow. */
    protected $attributes = [
        'lab_status' => 'pending_assignment',
    ];

    protected function casts(): array
    {
        return [
            'lab_status' => LensOrderStatus::class,
            'expected_date' => 'date',
            'received_date' => 'date',
            'od_pd' => 'decimal:1',
            'os_pd' => 'decimal:1',
            'od_height' => 'decimal:1',
            'os_height' => 'decimal:1',
            'frame_a' => 'decimal:1',
            'frame_b' => 'decimal:1',
            'frame_dbl' => 'decimal:1',
            'frame_type' => FrameType::class,
            'frame_source' => FrameSource::class,
            'prescription_snapshot' => 'array',
            'sent_at' => 'datetime',
            'customer_notified_at' => 'datetime',
            'remake_reason' => RemakeReason::class,
            'remake_responsible' => RemakeResponsible::class,
            'remake_cost' => 'integer',
        ];
    }

    /** @param  Builder<LensOrder>  $query */
    public function scopePending(Builder $query): void
    {
        // An order superseded by a remake is closed: its remake carries the work on. Cancelled ones have no work left.
        $query->whereNotIn('lab_status', [LensOrderStatus::Ready->value, LensOrderStatus::Cancelled->value])->whereDoesntHave('remakes');
    }

    public function isReady(): bool
    {
        return $this->lab_status === LensOrderStatus::Ready;
    }

    /**
     * What the lab still needs before the order can be sent, as field labels.
     * Heights matter only for multifocal lenses.
     *
     * @return array<int, string>
     */
    public function missingForSending(): array
    {
        $kind = $this->saleItem?->lensConfig?->lensCombination?->lensType?->kind;
        $multifocal = $kind instanceof LensKind && $kind->requiresAddition();

        $required = [
            'supplier_id' => __('app.fields.laboratory'),
            'od_pd' => __('app.fields.od_pd'),
            'os_pd' => __('app.fields.os_pd'),
            'frame_type' => __('app.fields.frame_type'),
            ...($multifocal ? ['od_height' => __('app.fields.od_height'), 'os_height' => __('app.fields.os_height')] : []),
        ];

        return array_values(array_filter(
            $required,
            fn (string $label, string $column): bool => blank($this->getAttribute($column)),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /** Hand the order to the lab: expected back after the lab's lead time in business days. */
    public function markSent(?CarbonInterface $at = null): void
    {
        $at ??= now();
        $leadTime = $this->supplier?->lead_time_days;

        $this->update([
            'lab_status' => LensOrderStatus::Sent,
            'sent_at' => $at,
            // ponytail: Mon–Fri only; Colombian holidays are not modeled. Add a holiday calendar if labs complain.
            'expected_date' => $leadTime === null ? null : $at->copy()->addWeekdays($leadTime)->toDateString(),
        ]);
    }

    /**
     * Order the lens again for the same sale item. The new order starts
     * pending at the same lab (unless another is given) with every technical
     * field copied; a store-caused remake keeps its cost so it lowers the
     * sale's real margin, any other responsible party costs the store nothing.
     */
    public function remake(RemakeReason $reason, RemakeResponsible $responsible, int $cost, ?int $supplierId = null, ?string $notes = null): self
    {
        if (in_array($this->lab_status, [LensOrderStatus::PendingAssignment, LensOrderStatus::Cancelled], true) || $this->remakes()->exists()) {
            throw new DomainException(__('app.lab_order.cannot_remake'));
        }

        // Reload so columns left to their DB default (e.g. frame_source) are copied too.
        $copy = $this->fresh()->only([
            'company_id', 'sale_item_id', 'od_pd', 'os_pd', 'od_height', 'os_height',
            'frame_a', 'frame_b', 'frame_dbl', 'frame_type', 'frame_source',
            'customer_frame_description', 'customer_frame_condition', 'prescription_snapshot',
        ]);

        return self::create([
            ...$copy,
            'supplier_id' => $supplierId ?? $this->supplier_id,
            'lab_status' => LensOrderStatus::PendingAssignment,
            'remake_of_id' => $this->id,
            'remake_reason' => $reason,
            'remake_responsible' => $responsible,
            'remake_cost' => $responsible === RemakeResponsible::Store ? $cost : 0,
            'notes' => $notes,
        ]);
    }

    /** The order this one remakes. */
    public function remakeOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'remake_of_id');
    }

    /** Remakes of this order. */
    public function remakes(): HasMany
    {
        return $this->hasMany(self::class, 'remake_of_id');
    }

    /** How the lab should identify the frame: the armado's sold frame line, or the customer's own frame. */
    public function frameDescription(): ?string
    {
        if ($this->frame_source === FrameSource::CustomerOwn) {
            return $this->customer_frame_description;
        }

        $lensItem = $this->saleItem;

        return $lensItem?->group_key === null ? null : SaleItem::query()
            ->where('sale_id', $lensItem->sale_id)
            ->where('group_key', $lensItem->group_key)
            ->whereKeyNot($lensItem->id)
            ->whereHas('product.category', fn ($query) => $query->where('key', 'frame'))
            ->value('description');
    }

    /** Who hears that the glasses are ready: the sale's customer (the payer), else the armado's patient. */
    public function customerToNotify(): ?Customer
    {
        $this->loadMissing(['saleItem.sale.customer', 'saleItem.lensConfig.patient']);

        return $this->saleItem?->sale?->customer ?? $this->saleItem?->lensConfig?->patient;
    }

    /** The wa.me link with the order-ready message, or null without a usable phone. */
    public function customerNoticeUrl(): ?string
    {
        $customer = $this->customerToNotify();
        $sale = $this->saleItem?->sale;

        if ($customer === null || $sale === null) {
            return null;
        }

        return WhatsApp::url($customer->phone, MessageTemplate::render($this->company_id, MessageTemplateKey::OrderReady, [
            'cliente' => $customer->name,
            'orden' => $sale->number,
            'saldo' => $sale->balance,
        ]));
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
