<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\LensTechnologyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'is_active', 'sort_order'])]
class LensTechnology extends Model
{
    /** @use HasFactory<LensTechnologyFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** A catalog entry still referenced by a combination cannot be deleted. */
    protected static function booted(): void
    {
        static::deleting(fn (LensTechnology $model): bool => ! $model->hasChildren());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function combinations(): HasMany
    {
        return $this->hasMany(LensCombination::class, 'lens_technology_id');
    }

    public function hasChildren(): bool
    {
        return $this->combinations()->exists();
    }
}
