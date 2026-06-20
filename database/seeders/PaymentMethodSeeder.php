<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::updateOrCreate(
            ['name' => 'Efectivo'],
            ['is_active' => true, 'is_default' => true, 'sort_order' => 0],
        );
    }
}
