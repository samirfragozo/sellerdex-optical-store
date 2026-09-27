<?php

namespace App\Models;

use App\Enums\TaxTreatment;
use App\Traits\BelongsToCompany;
use Database\Factories\TaxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'name', 'rate', 'treatment', 'dian_code', 'is_default', 'is_active', 'is_system'])]
class Tax extends Model
{
    /** @use HasFactory<TaxFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * Taxes every company starts with. `dian_code` 01 is IVA in DIAN's tax table.
     *
     * @var list<array{name: string, rate: int, treatment: TaxTreatment, dian_code: string|null, is_default: bool}>
     */
    public const DEFAULTS = [
        ['name' => 'IVA 19%', 'rate' => 19, 'treatment' => TaxTreatment::Taxed, 'dian_code' => '01', 'is_default' => true],
        ['name' => 'IVA 5%', 'rate' => 5, 'treatment' => TaxTreatment::Taxed, 'dian_code' => '01', 'is_default' => false],
        ['name' => 'Exento', 'rate' => 0, 'treatment' => TaxTreatment::Exempt, 'dian_code' => '01', 'is_default' => false],
        ['name' => 'Excluido', 'rate' => 0, 'treatment' => TaxTreatment::Excluded, 'dian_code' => null, 'is_default' => false],
    ];

    /** System taxes and taxes still referenced are never deleted (only deactivated or renamed). */
    protected static function booted(): void
    {
        static::deleting(fn (Tax $tax): bool => ! $tax->is_system && ! $tax->isInUse());
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'treatment' => TaxTreatment::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public static function seedDefaultsFor(Company $company): void
    {
        foreach (self::DEFAULTS as $tax) {
            static::withoutGlobalScopes()->create([...$tax, 'company_id' => $company->id, 'is_active' => true, 'is_system' => true]);
        }
    }

    /** True while a product, category or lens combination references this tax. */
    public function isInUse(): bool
    {
        // ponytail: no table references taxes yet; Task 2 adds tax_id columns and replaces this body.
        return false;
    }
}
