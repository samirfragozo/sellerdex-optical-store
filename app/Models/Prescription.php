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
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'company_id', 'customer_id', 'created_by', 'exam_date',
    'od_sphere', 'od_cylinder', 'od_axis', 'od_add', 'od_prism', 'od_prism_base', 'od_va', 'od_pd',
    'os_sphere', 'os_cylinder', 'os_axis', 'os_add', 'os_prism', 'os_prism_base', 'os_va', 'os_pd',
    'prescriber_name', 'prescriber_license', 'attachment', 'filters', 'diagnosis', 'notes',
])]
class Prescription extends Model
{
    /** @use HasFactory<PrescriptionFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** Columns stored as decimals; see setAttribute(). */
    private const DECIMAL_COLUMNS = [
        'od_sphere', 'od_cylinder', 'od_add', 'od_prism', 'od_pd',
        'os_sphere', 'os_cylinder', 'os_add', 'os_prism', 'os_pd',
    ];

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
            'customer_id' => 'integer',
            'od_prism_base' => PrismBase::class,
            'os_prism_base' => PrismBase::class,
            'filters' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Registered after BelongsToCompany's `creating` listener, so company_id is set.
        static::creating(fn (Prescription $rx) => $rx->deriveExpiry());
        static::updating(fn (Prescription $rx) => $rx->deriveExpiry());

        // Health data: never keep an orphaned scan of a replaced/cleared attachment or a purged prescription.
        static::updated(function (Prescription $rx): void {
            if ($rx->wasChanged('attachment') && filled($old = $rx->getOriginal('attachment'))) {
                Storage::disk('local')->delete($old);
            }
        });
        static::forceDeleted(function (Prescription $rx): void {
            if (filled($rx->attachment)) {
                Storage::disk('local')->delete($rx->attachment);
            }
        });
    }

    /**
     * Normalize decimal input ("1,25", " +1.25 ", "") so the decimal casts can read it back.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function setAttribute($key, $value): mixed
    {
        if (is_string($value) && in_array($key, self::DECIMAL_COLUMNS, true)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
            $value = $value === '' ? null : $value;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Expiry follows the exam date only: a later change to the company validity
     * does not move existing expiries.
     */
    private function deriveExpiry(): void
    {
        if ($this->exam_date === null || ($this->expires_at !== null && ! $this->isDirty('exam_date'))) {
            return;
        }

        $months = Company::query()->whereKey($this->company_id)->value('prescription_validity_months') ?? 12;
        $this->expires_at = $this->exam_date->copy()->addMonthsNoOverflow((int) $months);
    }

    public function isExpired(?CarbonInterface $at = null): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(($at ?? now())->copy()->startOfDay());
    }

    /**
     * The eye with the highest power — its strongest meridian,
     * max(|sphere|, |sphere + cylinder|) — which decides the lab price
     * range. Ties go to OD; blank values count as 0.
     *
     * @return array{sphere: float, cylinder: float, add: float}
     */
    public function governingEye(): array
    {
        $eye = fn (string $side): array => [
            'sphere' => (float) ($this->{"{$side}_sphere"} ?? 0),
            'cylinder' => (float) ($this->{"{$side}_cylinder"} ?? 0),
            'add' => (float) ($this->{"{$side}_add"} ?? 0),
        ];
        $power = fn (array $values): float => max(abs($values['sphere']), abs($values['sphere'] + $values['cylinder']));

        [$od, $os] = [$eye('od'), $eye('os')];

        return $power($os) > $power($od) ? $os : $od;
    }

    /**
     * Shape this prescription as a POS "option": the list entry the prescription
     * step shows (existing prescriptions on page load, or the one just saved).
     *
     * @return array{id: int, customer_id: int, exam_date: ?string, expires_at: ?string, is_expired: bool, summary: string}
     */
    public function toPosOption(): array
    {
        return [
            // The primary key has no model cast, so a prescription just created
            // from a form-encoded POST still holds it as a string until the
            // model is refreshed from the database — cast explicitly, since the
            // POS frontend compares this id with `===`. customer_id doesn't
            // need the same treatment: it has an `integer` cast above.
            'id' => (int) $this->id,
            'customer_id' => $this->customer_id,
            'exam_date' => $this->exam_date?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'is_expired' => $this->isExpired(),
            'summary' => sprintf('OD %s / OS %s', self::formatDiopter($this->od_sphere) ?: '—', self::formatDiopter($this->os_sphere) ?: '—'),
        ];
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

    /** Armados sold on this prescription. */
    public function lensConfigs(): HasMany
    {
        return $this->hasMany(SaleItemLensConfig::class);
    }
}
