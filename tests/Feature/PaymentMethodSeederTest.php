<?php

use App\Models\PaymentMethod;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds payment methods with platform surcharges', function () {
    $this->seed(PaymentMethodSeeder::class);
    $this->seed(PaymentMethodSeeder::class); // idempotent

    expect(PaymentMethod::where('name', 'Efectivo')->where('is_default', true)->count())->toBe(1)
        ->and((float) PaymentMethod::where('name', 'Addi')->value('surcharge_percent'))->toBe(7.0)
        ->and((float) PaymentMethod::where('name', 'Sistecrédito')->value('surcharge_percent'))->toBe(7.0)
        ->and((float) PaymentMethod::where('name', 'Nequi Lina')->value('surcharge_percent'))->toBe(0.0)
        ->and(PaymentMethod::whereIn('name', ['Efectivo', 'Nequi Lina', 'Nequi Samir', 'Daviplata Lina', 'Datáfono', 'Addi', 'Sistecrédito'])->count())->toBe(7);
});
