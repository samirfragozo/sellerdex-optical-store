<?php

use App\Models\Company;
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

it('rejects spending more credit than the customer has with a specific message', function () {
    $this->postJson(route('pos.store'), storeCreditSale($this->customer->id, 40_000))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payments' => __('app.validation.store_credit_exceeds_balance', ['balance' => '$30.000'])]);

    expect(Sale::count())->toBe(0);
});

it('sums several store credit payments against the balance', function () {
    $payload = storeCreditSale($this->customer->id, 20_000);
    $payload['payments'][] = ['payment_method_id' => $this->credit->id, 'amount' => 20_000];

    $this->postJson(route('pos.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payments' => __('app.validation.store_credit_exceeds_balance', ['balance' => '$30.000'])]);

    expect(Sale::count())->toBe(0);
});

it('rejects store credit without a customer or with an inline new customer', function () {
    $message = __('app.validation.store_credit_needs_customer');

    $this->postJson(route('pos.store'), storeCreditSale(null, 10_000))
        ->assertStatus(422)->assertJsonValidationErrors(['payments' => $message]);

    $this->postJson(route('pos.store'), storeCreditSale(null, 10_000) + ['customer' => [
        'name' => 'Nuevo', 'last_name' => 'Cliente', 'document_type' => 'CC', 'id_number' => '12345', 'phone' => '3001234567',
    ]])->assertStatus(422)->assertJsonValidationErrors(['payments' => $message]);

    expect(Sale::count())->toBe(0);
});

it('rejects spending the credit of a customer from another company', function () {
    $foreign = Customer::factory()->for(Company::factory()->create())->create();
    CustomerCredit::withoutGlobalScopes()->create(['company_id' => $foreign->company_id, 'customer_id' => $foreign->id, 'amount' => 50_000]);

    $this->postJson(route('pos.store'), storeCreditSale($foreign->id, 10_000))
        ->assertStatus(422)->assertJsonValidationErrors('customer_id');

    expect(Sale::count())->toBe(0);
});

it('never charges a surcharge on the store credit part of a payment', function () {
    $this->credit->update(['surcharge_percent' => 5]);

    $this->postJson(route('pos.store'), storeCreditSale($this->customer->id, 30_000))->assertOk();

    expect((float) Sale::sole()->surcharge_percent)->toBe(0.0);
});

it('shows the customer credit in the POS search and keeps the method out of the normal list', function () {
    $this->getJson(route('pos.customers.search', ['q' => $this->customer->name]))
        ->assertOk()->assertJsonPath('0.credit_balance', 30_000);

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page
        ->where('storeCreditMethodId', $this->credit->id)
        ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('id')->doesntContain($this->credit->id)));
});
