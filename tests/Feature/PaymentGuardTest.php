<?php

use App\Actions\RegisterSale;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    $this->method = PaymentMethod::factory()->create(['is_active' => true]);
    $this->product = Product::factory()->create(['price' => 100_000]);
});

function guardPayload(int $amount): array
{
    return [
        'document_type' => 'order',
        'products' => [['product_id' => test()->product->id, 'description' => 'X', 'quantity' => 1, 'unit_price' => 100_000]],
        'payments' => [['payment_method_id' => test()->method->id, 'amount' => $amount]],
    ];
}

it('accepts paying exactly the computed total', function () {
    $sale = app(RegisterSale::class)->handle(guardPayload(100_000), $this->seller);

    expect($sale->totalPaid())->toBe(100_000);
});

it('rejects payments above the computed total and saves nothing', function () {
    expect(fn () => app(RegisterSale::class)->handle(guardPayload(100_001), $this->seller))
        ->toThrow(ValidationException::class);

    expect(Sale::count())->toBe(0);
});

it('returns a 422 with a payments error from the POS', function () {
    openCashRegisterSession($this->seller);

    $this->postJson(route('pos.store'), guardPayload(100_001))
        ->assertStatus(422)
        ->assertJsonValidationErrors('payments');
});
