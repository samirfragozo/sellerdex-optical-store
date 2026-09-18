<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\LensPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'price', 'cost', 'is_active', 'sort_order'])]
class LensPackage extends Model
{
    /** @use HasFactory<LensPackageFactory> */
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
