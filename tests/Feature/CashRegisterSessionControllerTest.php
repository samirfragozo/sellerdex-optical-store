<?php

use App\Models\CashRegisterSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('opens a cash register session for the authenticated user', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->postJson('/pos/cash-sessions', ['opening_cash' => 100_000])
        ->assertOk()
        ->assertJson(['opening_cash' => 100_000, 'closed_at' => null]);

    expect(CashRegisterSession::where('user_id', $seller->id)->whereNull('closed_at')->exists())->toBeTrue();
});

it('rejects opening a second session while one is already open', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);

    $this->actingAs($seller)
        ->postJson('/pos/cash-sessions', ['opening_cash' => 0])
        ->assertStatus(422);

    expect(CashRegisterSession::where('user_id', $seller->id)->count())->toBe(1);
});

it('closes a session and computes expected cash from the cash payments received since it opened', function () {
    $seller = User::factory()->seller()->create();
    $cashMethod = PaymentMethod::factory()->create(['is_default' => true]);
    $cardMethod = PaymentMethod::factory()->create(['is_default' => false]);
    $session = openCashRegisterSession($seller, 50_000);

    $sale = Sale::factory()->create();
    Payment::factory()->create([
        'sale_id' => $sale->id,
        'payment_method_id' => $cashMethod->id,
        'received_by' => $seller->id,
        'amount' => 80_000,
    ]);
    // Not counted: a different payment method.
    Payment::factory()->create([
        'sale_id' => $sale->id,
        'payment_method_id' => $cardMethod->id,
        'received_by' => $seller->id,
        'amount' => 40_000,
    ]);

    $this->actingAs($seller)
        ->postJson("/pos/cash-sessions/{$session->id}/close", ['closed_cash' => 135_000])
        ->assertOk()
        ->assertJson(['expected_cash' => 130_000, 'difference' => 5_000]);

    expect($session->fresh()->closed_at)->not->toBeNull();
});

it('forbids closing another user session', function () {
    $owner = User::factory()->seller()->create();
    $intruder = User::factory()->seller()->create();
    $session = openCashRegisterSession($owner);

    $this->actingAs($intruder)
        ->postJson("/pos/cash-sessions/{$session->id}/close", ['closed_cash' => 0])
        ->assertForbidden();
});

it('rejects closing an already closed session', function () {
    $seller = User::factory()->seller()->create();
    $session = openCashRegisterSession($seller);
    $session->update(['closed_at' => now(), 'closed_cash' => 0, 'expected_cash' => 0]);

    $this->actingAs($seller)
        ->postJson("/pos/cash-sessions/{$session->id}/close", ['closed_cash' => 0])
        ->assertStatus(422);
});
