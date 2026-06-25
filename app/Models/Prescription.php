<?php

namespace App\Models;

use App\Enums\LensType;
use Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer_id', 'sale_id', 'created_by', 'exam_date',
    'od_sphere', 'od_cylinder', 'od_axis', 'od_add', 'od_va', 'od_pd',
    'os_sphere', 'os_cylinder', 'os_axis', 'os_add', 'os_va', 'os_pd',
    'lens_type', 'filters', 'usage', 'control_period', 'diagnosis', 'drops', 'lensometry',
])]
class Prescription extends Model
{
    /** @use HasFactory<PrescriptionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'filters' => 'array',
            'lens_type' => LensType::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** Sales (documents) issued using this prescription. */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
