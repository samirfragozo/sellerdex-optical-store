<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\LensTechnologyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'is_active', 'sort_order'])]
class LensTechnology extends Model
{
    /** @use HasFactory<LensTechnologyFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
