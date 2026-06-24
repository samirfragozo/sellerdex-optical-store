<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        // name => [is_default, surcharge_percent, sort_order]
        $methods = [
            'Efectivo' => [true, 0, 0],
            'Nequi Lina' => [false, 0, 1],
            'Nequi Samir' => [false, 0, 2],
            'Daviplata Lina' => [false, 0, 3],
            'Datáfono' => [false, 0, 4],
            'Addi' => [false, 7, 5],
            'Sistecrédito' => [false, 7, 6],
        ];

        foreach ($methods as $name => [$isDefault, $surcharge, $sort]) {
            PaymentMethod::updateOrCreate(
                ['name' => $name],
                ['is_active' => true, 'is_default' => $isDefault, 'surcharge_percent' => $surcharge, 'sort_order' => $sort],
            );
        }
    }
}
