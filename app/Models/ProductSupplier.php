<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductSupplier extends Pivot
{
    protected $table = 'product_supplier';

    protected function casts(): array
    {
        return [
            'supplier_cost' => 'integer',
            'lead_time_days' => 'integer',
            'is_preferred' => 'boolean',
        ];
    }
}
