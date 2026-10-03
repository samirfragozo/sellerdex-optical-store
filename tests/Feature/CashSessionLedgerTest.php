<?php

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Support\Readiness\SaleReadiness;
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
    expect(CashRegisterSession::suggestedOpeningCash($this->cashier->company))->toBe(0);

    $this->session->update(['closed_at' => now(), 'closed_cash' => 120_000, 'expected_cash' => 120_000, 'cash_left' => 40_000]);

    expect(CashRegisterSession::suggestedOpeningCash($this->cashier->company))->toBe(40_000);
});

it('ignores other companies when suggesting the float and picks the latest close', function () {
    $this->session->update(['closed_at' => now()->subHours(3), 'closed_cash' => 1, 'expected_cash' => 1, 'cash_left' => 10_000]);
    $later = openCashRegisterSession($this->cashier, 0);
    $later->update(['closed_at' => now()->subHour(), 'closed_cash' => 1, 'expected_cash' => 1, 'cash_left' => 25_000]);
    openCashRegisterSession($this->cashier, 0); // still open: not a close

    $otherUser = User::factory()->seller()->create();
    $otherSession = openCashRegisterSession($otherUser, 0);
    $otherSession->update(['closed_at' => now(), 'closed_cash' => 1, 'expected_cash' => 1, 'cash_left' => 77_000]);

    expect(CashRegisterSession::suggestedOpeningCash($this->cashier->company))->toBe(25_000)
        ->and(CashRegisterSession::suggestedOpeningCash($otherUser->company))->toBe(77_000);
});

it('computes expected amounts for its own shop whoever is logged in', function () {
    Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->cash->id, 'amount' => 30_000, 'received_by' => $this->cashier->id]);
    Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->card->id, 'amount' => 80_000, 'received_by' => $this->cashier->id]);
    Expense::factory()->create(['payment_method_id' => $this->cash->id, 'amount' => 5_000, 'created_by' => $this->cashier->id]);
    CashMovement::factory()->create(['cash_register_session_id' => $this->session->id, 'type' => CashMovementType::Income, 'amount' => 10_000]);

    $expected = [$this->cash->id => 85_000, $this->card->id => 80_000];
    ksort($expected);
    expect($this->session->fresh()->expectedByMethod())->toBe($expected);

    $otherUser = User::factory()->seller()->create();
    PaymentMethod::withoutGlobalScopes()->where('company_id', $otherUser->company_id)->where('is_default', true)->exists()
        || PaymentMethod::factory()->create(['is_default' => true, 'company_id' => $otherUser->company_id]);
    $session = $this->session->fresh();

    $this->actingAs($otherUser);
    expect($session->expectedByMethod())->toBe($expected)
        ->and($session->expectedCash())->toBe(85_000);

    $this->actingAs(User::factory()->superadmin()->create());
    expect($session->expectedByMethod())->toBe($expected);
});

it('is stale once a later day starts with it still open', function () {
    expect($this->session->isStale())->toBeFalse();

    Carbon::setTestNow(now()->addDay()->startOfDay()->addHour());

    expect($this->session->fresh()->isStale())->toBeTrue();
});

it('keeps a session open at 09:00 Bogota fresh at 19:30 Bogota and stale the next morning', function () {
    // The beforeEach session would itself go stale on this date.
    $this->session->update(['closed_at' => now()]);
    Carbon::setTestNow(Carbon::parse('2026-10-05 09:00', 'America/Bogota'));
    $session = openCashRegisterSession($this->cashier, 0);
    $hasStaleIssue = fn (): bool => collect(SaleReadiness::for($this->cashier->company))->contains(fn ($issue): bool => $issue->key === 'cash_session_stale');

    Carbon::setTestNow(Carbon::parse('2026-10-05 19:30', 'America/Bogota'));
    expect($session->fresh()->isStale())->toBeFalse()
        ->and($hasStaleIssue())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-10-06 08:00', 'America/Bogota'));
    expect($session->fresh()->isStale())->toBeTrue()
        ->and($hasStaleIssue())->toBeTrue();
});

afterEach(fn () => Carbon::setTestNow());
