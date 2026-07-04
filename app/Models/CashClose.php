<?php

namespace App\Models;

use App\Enums\CashCloseStatus;
use App\Enums\CashCloseType;
use App\Traits\BelongsToCompany;
use Database\Factories\CashCloseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'type', 'period_start', 'period_end', 'opening_cash', 'total_sales', 'total_collected', 'collected_by_method', 'total_expenses', 'total_receivable', 'expected_cash', 'counted_cash', 'difference', 'status', 'closed_by', 'closed_at', 'notes'])]
class CashClose extends Model
{
    /** @use HasFactory<CashCloseFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CashCloseType::class,
            'status' => CashCloseStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'collected_by_method' => 'array',
            'opening_cash' => 'integer',
            'total_sales' => 'integer',
            'total_collected' => 'integer',
            'total_expenses' => 'integer',
            'total_receivable' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'difference' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the difference in sync with counted vs expected cash.
        static::saving(function (CashClose $cashClose): void {
            $cashClose->difference = $cashClose->counted_cash - $cashClose->expected_cash;
        });
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
