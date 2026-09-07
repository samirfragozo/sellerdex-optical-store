<?php

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps the difference in sync when both closed_cash and expected_cash are set', function () {
    $session = CashRegisterSession::factory()->create([
        'opening_cash' => 50_000,
    ]);

    $session->update(['closed_cash' => 120_000, 'expected_cash' => 100_000]);

    expect($session->fresh()->difference)->toBe(20_000);
});

it('leaves difference null until both closed_cash and expected_cash are present', function () {
    $session = CashRegisterSession::factory()->create();

    expect($session->difference)->toBeNull();
});

it('belongs to the user who opened it', function () {
    $user = User::factory()->create();
    $session = CashRegisterSession::factory()->for($user)->create();

    expect($session->user->is($user))->toBeTrue();
});
