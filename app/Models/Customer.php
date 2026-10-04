<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Traits\BelongsToCompany;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'last_name', 'document_type', 'id_number', 'phone', 'address', 'city', 'birth_date', 'email', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'birth_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The `prescriptions.customer_id` FK cascades at the DB level on force
        // delete, but that bypasses Eloquent — Prescription::forceDeleted (which
        // purges the attachment from disk) would never fire. Force-delete each
        // prescription explicitly first so its own event still runs.
        static::forceDeleting(function (Customer $customer): ?bool {
            // Fiscal documents are accounting records: the sales cascade would hit their restricting FK.
            if ($customer->hasFiscalDocuments()) {
                return false;
            }

            $customer->prescriptions()->withTrashed()->get()->each->forceDelete();

            return null;
        });
    }

    /** Whether any of the customer's sales (even trashed) holds a fiscal document. */
    public function hasFiscalDocuments(): bool
    {
        return FiscalDocument::withoutGlobalScopes()
            ->whereIn('sale_id', Sale::withoutGlobalScopes()->withTrashed()->where('customer_id', $this->id)->select('id'))
            ->exists();
    }

    /** Full name (first name + last name). */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->name} {$this->last_name}"));
    }

    /** Age computed from the date of birth. */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->birth_date?->age);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class);
    }

    /** Store credit the customer can still spend: the sum of the ledger. */
    public function creditBalance(): int
    {
        return (int) CustomerCredit::withoutGlobalScopes()->where('customer_id', $this->id)->sum('amount');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }
}
