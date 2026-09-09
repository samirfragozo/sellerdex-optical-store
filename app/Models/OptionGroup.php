<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\OptionGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'selection_type', 'is_required', 'is_active'])]
class OptionGroup extends Model
{
    /** @use HasFactory<OptionGroupFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }
}
