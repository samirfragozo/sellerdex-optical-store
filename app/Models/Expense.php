<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['company_id', 'expense_category_id', 'description', 'amount', 'payment_method_id', 'spent_at', 'created_by', 'notes'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'spent_at' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['description', 'amount', 'expense_category_id', 'spent_at'])
            ->logOnlyDirty();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
