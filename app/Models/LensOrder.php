<?php

namespace App\Models;

use App\Enums\FrameSource;
use App\Enums\FrameType;
use App\Enums\LensOrderStatus;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Traits\BelongsToCompany;
use Database\Factories\LensOrderFactory;
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
        // An order superseded by a remake is closed: its remake carries the work on.
        $query->where('lab_status', '!=', LensOrderStatus::Ready->value)->whereDoesntHave('remakes');
    }

    public function isReady(): bool
    {
        return $this->lab_status === LensOrderStatus::Ready;
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

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
