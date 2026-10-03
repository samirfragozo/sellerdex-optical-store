<?php

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->cashier = User::factory()->seller()->create();
    $this->actingAs($this->cashier);
    $this->cash = PaymentMethod::where('is_default', true)->first()
        ?? PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    $this->card = PaymentMethod::factory()->create(['name' => 'Tarjeta']);
    $this->session = openCashRegisterSession($this->cashier, 50_000);
});

it('stamps payments and cash expenses with the open session of who received or spent the money', function () {
    $payment = Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->cash->id, 'amount' => 30_000, 'received_by' => $this->cashier->id]);
    $cashExpense = Expense::factory()->create(['payment_method_id' => $this->cash->id, 'amount' => 5_000, 'created_by' => $this->cashier->id]);
    $cardExpense = Expense::factory()->create(['payment_method_id' => $this->card->id, 'amount' => 9_000, 'created_by' => $this->cashier->id]);

    expect($payment->cash_register_session_id)->toBe($this->session->id)
        ->and($cashExpense->cash_register_session_id)->toBe($this->session->id)
        ->and($cardExpense->cash_register_session_id)->toBeNull();
});

it('expects per method from the session own payments, movements and cash expenses only', function () {
    $other = User::factory()->seller()->create(['company_id' => $this->cashier->company_id]);
    openCashRegisterSession($other, 0);

    Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->cash->id, 'amount' => 30_000, 'received_by' => $this->cashier->id]);
    Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->card->id, 'amount' => 80_000, 'received_by' => $this->cashier->id]);
    Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->cash->id, 'amount' => 99_000, 'received_by' => $other->id]);
    Expense::factory()->create(['payment_method_id' => $this->cash->id, 'amount' => 5_000, 'created_by' => $this->cashier->id]);
    CashMovement::factory()->create(['cash_register_session_id' => $this->session->id, 'type' => CashMovementType::Income, 'amount' => 10_000]);
    CashMovement::factory()->create(['cash_register_session_id' => $this->session->id, 'type' => CashMovementType::Withdrawal, 'amount' => 20_000]);

    $expected = [
        $this->cash->id => 50_000 + 30_000 + 10_000 - 20_000 - 5_000,
        $this->card->id => 80_000,
    ];
    ksort($expected); // expectedByMethod() returns keys in ascending id order

    expect($this->session->fresh()->expectedByMethod())->toBe($expected)
        ->and($this->session->fresh()->expectedCash())->toBe(65_000);
});

it('suggests the float from the cash left at the company last close', function () {
    expect(CashRegisterSession::suggestedOpeningCash())->toBe(0);

    $this->session->update(['closed_at' => now(), 'closed_cash' => 120_000, 'expected_cash' => 120_000, 'cash_left' => 40_000]);

    expect(CashRegisterSession::suggestedOpeningCash())->toBe(40_000);
});

it('is stale once a later day starts with it still open', function () {
    expect($this->session->isStale())->toBeFalse();

    Carbon::setTestNow(now()->addDay()->startOfDay()->addHour());

    expect($this->session->fresh()->isStale())->toBeTrue();
});

afterEach(fn () => Carbon::setTestNow());
