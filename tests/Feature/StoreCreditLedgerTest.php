<?php

use App\Actions\SeedCompanyDefaults;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->credit = PaymentMethod::storeCreditFor($this->admin->company_id)
        ?? PaymentMethod::factory()->create(['name' => 'Saldo a favor', 'is_store_credit' => true]);
    $this->customer = Customer::factory()->create();
});

it('grants credit with a negative store-credit payment and spends it with a positive one', function () {
    $old = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $old->id, 'payment_method_id' => $this->credit->id, 'amount' => -30_000]);
    expect($this->customer->creditBalance())->toBe(30_000);

    $new = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $new->id, 'payment_method_id' => $this->credit->id, 'amount' => 20_000]);
    expect($this->customer->fresh()->creditBalance())->toBe(10_000);
});

it('refuses to spend more credit than the customer has, or on a sale without customer', function () {
    $sale = Sale::factory()->create(['customer_id' => $this->customer->id]);

    expect(fn () => Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => 1]))
        ->toThrow(ValidationException::class);

    $anonymous = Sale::factory()->create(['customer_id' => null]);
    expect(fn () => Payment::factory()->create(['sale_id' => $anonymous->id, 'payment_method_id' => $this->credit->id, 'amount' => -5_000]))
        ->toThrow(ValidationException::class)
        ->and(Payment::count())->toBe(0);
});

it('gives the credit back when a store-credit payment is deleted', function () {
    $sale = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => -30_000]);
    $spend = Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => 10_000]);
    expect($this->customer->creditBalance())->toBe(20_000);

    $spend->delete();

    expect($this->customer->fresh()->creditBalance())->toBe(30_000);
});

it('keeps the store-credit method undeletable and out of the cash count', function () {
    expect($this->credit->delete())->toBeFalse()
        ->and($this->credit->isProtected())->toBeTrue();

    $session = openCashRegisterSession($this->admin, 0);
    $sale = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => -5_000, 'received_by' => $this->admin->id]);

    expect(Payment::first()->cash_register_session_id)->toBe($session->id)
        ->and(array_keys($session->fresh()->expectedByMethod()))->not->toContain($this->credit->id);
});

it('seeds the Saldo a favor method for a new company', function () {
    $company = Company::factory()->create();
    app(SeedCompanyDefaults::class)->handle($company);

    $method = PaymentMethod::storeCreditFor($company->id);

    expect($method)->not->toBeNull()
        ->and($method->name)->toBe('Saldo a favor')
        ->and($method->is_default)->toBeFalse()
        ->and(PaymentMethod::storeCreditFor($this->admin->company_id)?->company_id)->toBe($this->admin->company_id);
});
