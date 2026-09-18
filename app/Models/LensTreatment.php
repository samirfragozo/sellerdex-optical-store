<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\LensTreatmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'price', 'cost', 'is_active', 'sort_order'])]
class LensTreatment extends Model
{
    /** @use HasFactory<LensTreatmentFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
