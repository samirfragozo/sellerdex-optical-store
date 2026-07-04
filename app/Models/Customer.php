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
}
