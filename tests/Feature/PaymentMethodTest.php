<?php

use App\Models\PaymentMethod;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('siembra Efectivo como método por defecto protegido', function () {
    $this->seed(PaymentMethodSeeder::class);

    $cash = PaymentMethod::where('is_default', true)->first();

    expect($cash)->not->toBeNull()
        ->and($cash->name)->toBe('Efectivo')
        ->and($cash->is_active)->toBeTrue()
        ->and($cash->isProtected())->toBeTrue();
});

it('no duplica Efectivo si se siembra dos veces', function () {
    $this->seed(PaymentMethodSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    expect(PaymentMethod::where('name', 'Efectivo')->count())->toBe(1);
});

it('no permite eliminar el método por defecto (Efectivo), ni siquiera el admin', function () {
    $this->seed(PaymentMethodSeeder::class);
    $cash = PaymentMethod::where('is_default', true)->first();

    $cash->delete();

    expect(PaymentMethod::whereKey($cash->getKey())->exists())->toBeTrue();
});

it('permite eliminar un método de pago no protegido', function () {
    $method = PaymentMethod::factory()->create(['is_default' => false]);

    $method->delete();

    expect(PaymentMethod::whereKey($method->getKey())->exists())->toBeFalse();
});
