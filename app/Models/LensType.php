<?php

namespace App\Models;

use App\Enums\LensKind;
use App\Traits\BelongsToCompany;
use Database\Factories\LensTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'kind', 'is_active', 'sort_order'])]
class LensType extends Model
{
    /** @use HasFactory<LensTypeFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** A catalog entry still referenced by a combination cannot be deleted. */
    protected static function booted(): void
    {
        static::deleting(fn (LensType $model): bool => ! $model->hasChildren());
    }

    protected function casts(): array
    {
        return [
            'kind' => LensKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function combinations(): HasMany
    {
        return $this->hasMany(LensCombination::class, 'lens_type_id');
    }

    public function hasChildren(): bool
    {
        return $this->combinations()->exists();
    }
}
