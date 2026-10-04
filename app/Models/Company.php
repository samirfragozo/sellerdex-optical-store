<?php

namespace App\Models;

use App\Enums\ArmadoFramePriceMode;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Enums\LayawayInvoicing;
use App\Enums\ReadinessSeverity;
use App\Enums\VatRegime;
use App\Support\Readiness\ReadinessIssue;
use App\Support\Readiness\SaleReadiness;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'tax_id', 'vat_regime', 'sale_number_prefix', 'next_sale_number', 'prescription_validity_months', 'address', 'phones', 'logo', 'is_active', 'plan', 'onboarding_step', 'onboarded_at', 'armado_frame_price_mode', 'armado_frame_discount_percent', 'tracks_inventory', 'inventory_counted_at', 'blind_cash_count', 'cash_difference_note_threshold', 'layaway_cancellation_fee_percent', 'quote_validity_days', 'seller_max_discount_percent', 'adaptation_warranty_days', 'invoicing_mode', 'default_fiscal_document', 'invoice_resolution_expires_at', 'pos_resolution_expires_at', 'layaway_invoicing'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'vat_regime' => VatRegime::class,
            'next_sale_number' => 'integer',
            'prescription_validity_months' => 'integer',
            'onboarded_at' => 'datetime',
            'armado_frame_price_mode' => ArmadoFramePriceMode::class,
            'armado_frame_discount_percent' => 'decimal:2',
            'tracks_inventory' => 'boolean',
            'inventory_counted_at' => 'datetime',
            'blind_cash_count' => 'boolean',
            'cash_difference_note_threshold' => 'integer',
            'layaway_cancellation_fee_percent' => 'decimal:2',
            'quote_validity_days' => 'integer',
            'seller_max_discount_percent' => 'decimal:2',
            'adaptation_warranty_days' => 'integer',
            'invoicing_mode' => InvoicingMode::class,
            'default_fiscal_document' => FiscalDocumentType::class,
            'invoice_resolution_expires_at' => 'date',
            'pos_resolution_expires_at' => 'date',
            'layaway_invoicing' => LayawayInvoicing::class,
        ];
    }

    /** Whether the shop keeps stock and a kardex (null — not decided yet — counts as no). */
    public function tracksInventory(): bool
    {
        return $this->tracks_inventory === true;
    }

    public function needsOnboarding(): bool
    {
        return $this->onboarded_at === null;
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            $company->slug ??= self::uniqueSlug($company->name);
        });

        static::updating(function (Company $company): void {
            // Turning inventory on (again) starts from a physical count.
            if ($company->isDirty('tracks_inventory') && $company->tracks_inventory === true) {
                $company->inventory_counted_at = null;
            }
        });
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /** Returns the authenticated user's company. */
    public static function current(): self
    {
        return static::findOrFail(Auth::user()->company_id);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function kitSlots(): HasMany
    {
        return $this->hasMany(KitSlot::class);
    }

    public function lensTreatments(): HasMany
    {
        return $this->hasMany(LensTreatment::class);
    }

    /** What the frame of an armado is charged, per the company's frame-pricing setting. */
    public function armadoFrameUnitPrice(int $price): int
    {
        return match ($this->armado_frame_price_mode) {
            ArmadoFramePriceMode::Included => 0,
            ArmadoFramePriceMode::Normal => $price,
            ArmadoFramePriceMode::DiscountPercent => (int) round($price * (100 - (float) $this->armado_frame_discount_percent) / 100),
        };
    }

    public function laboratories(): HasMany
    {
        return $this->hasMany(Supplier::class)->where('is_laboratory', true);
    }

    /**
     * Take the company's next sale number (prefix + 6-digit counter) and advance
     * the counter. The row lock keeps concurrent sales from sharing a number.
     */
    public static function takeNextSaleNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId): string {
            $company = static::query()->lockForUpdate()->findOrFail($companyId);
            $number = $company->next_sale_number;
            $company->increment('next_sale_number');

            return ($company->sale_number_prefix ?? '').str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        });
    }

    /** @return list<ReadinessIssue> */
    public function saleReadiness(): array
    {
        return SaleReadiness::for($this);
    }

    /** True when nothing blocks a sale as a whole (lens-only blockers do not count). */
    public function isReadyToSell(): bool
    {
        return collect($this->saleReadiness())
            ->doesntContain(fn (ReadinessIssue $i) => $i->severity === ReadinessSeverity::Blocking && $i->scope === null);
    }
}
