<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductAddition extends Pivot
{
    protected $table = 'product_additions';

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
