<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'name', 'is_active'])]
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use BelongsToCompany, HasFactory;

    /** A category that still has expenses cannot be deleted (enforced even for super admin). */
    protected static function booted(): void
    {
        static::deleting(fn (ExpenseCategory $category): bool => ! $category->hasChildren());
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function hasChildren(): bool
    {
        return Expense::withTrashed()->where('expense_category_id', $this->id)->exists();
    }
}
