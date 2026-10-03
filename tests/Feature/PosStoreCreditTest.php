<?php

use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    openCashRegisterSession($this->seller);
    $this->credit = PaymentMethod::storeCreditFor($this->seller->company_id) ?? PaymentMethod::factory()->create(['name' => 'Saldo a favor', 'is_store_credit' => true]);
    $this->customer = Customer::factory()->create();
    CustomerCredit::create(['company_id' => $this->seller->company_id, 'customer_id' => $this->customer->id, 'amount' => 30_000]);
});

function storeCreditSale(?int $customerId, int $amount): array
{
    return [
        'document_type' => 'order', 'customer_id' => $customerId,
        'products' => [['description' => 'Estuche', 'quantity' => 1, 'unit_price' => 50_000]],
        'payments' => [['payment_method_id' => test()->credit->id, 'amount' => $amount]],
    ];
}

it('pays part of a sale with the customer store credit', function () {
    $this->postJson(route('pos.store'), storeCreditSale($this->customer->id, 30_000))->assertOk();

    expect($this->customer->creditBalance())->toBe(0)
        ->and(Sale::sole()->balance)->toBe(20_000);
});

it('rejects spending more credit than the customer has, or without a customer', function () {
    $this->postJson(route('pos.store'), storeCreditSale($this->customer->id, 40_000))
        ->assertStatus(422)->assertJsonValidationErrors('payments');
    $this->postJson(route('pos.store'), storeCreditSale(null, 10_000))
        ->assertStatus(422)->assertJsonValidationErrors('payments');

    expect(Sale::count())->toBe(0);
});

it('shows the customer credit in the POS search and keeps the method out of the normal list', function () {
    $this->getJson(route('pos.customers.search', ['q' => $this->customer->name]))
        ->assertOk()->assertJsonPath('0.credit_balance', 30_000);

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page
        ->where('storeCreditMethodId', $this->credit->id)
        ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('id')->doesntContain($this->credit->id)));
});
