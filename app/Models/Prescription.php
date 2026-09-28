<?php

namespace App\Models;

use App\Enums\PrismBase;
use App\Traits\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'company_id', 'customer_id', 'created_by', 'exam_date', 'expires_at',
    'od_sphere', 'od_cylinder', 'od_axis', 'od_add', 'od_prism', 'od_prism_base', 'od_va', 'od_pd',
    'os_sphere', 'os_cylinder', 'os_axis', 'os_add', 'os_prism', 'os_prism_base', 'os_va', 'os_pd',
    'prescriber_name', 'prescriber_license', 'attachment', 'filters', 'diagnosis', 'notes',
])]
class Prescription extends Model
{
    /** @use HasFactory<PrescriptionFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'expires_at' => 'date',
            'od_sphere' => 'decimal:2',
            'od_cylinder' => 'decimal:2',
            'od_add' => 'decimal:2',
            'od_prism' => 'decimal:2',
            'os_sphere' => 'decimal:2',
            'os_cylinder' => 'decimal:2',
            'os_add' => 'decimal:2',
            'os_prism' => 'decimal:2',
            'od_pd' => 'decimal:1',
            'os_pd' => 'decimal:1',
            'od_axis' => 'integer',
            'os_axis' => 'integer',
            'od_prism_base' => PrismBase::class,
            'os_prism_base' => PrismBase::class,
            'filters' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Prescription $rx): void {
            if ($rx->exam_date === null) {
                return;
            }

            // `saving` fires before BelongsToCompany fills company_id on `creating`.
            $companyId = $rx->company_id ?? Auth::user()?->company_id;
            $months = Company::query()->whereKey($companyId)->value('prescription_validity_months') ?? 12;
            $rx->expires_at = $rx->exam_date->copy()->addMonthsNoOverflow((int) $months);
        });
    }

    public function isExpired(?CarbonInterface $at = null): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(($at ?? now())->copy()->startOfDay());
    }

    /** Signed diopter for display: +1.25 / -0.75 / 0.00, '' when missing. */
    public static function formatDiopter(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;

        return $number === 0.0 ? '0.00' : sprintf('%+.2f', $number);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Sales (documents) issued using this prescription. */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
