<?php

use App\Enums\CashCloseStatus;
use App\Models\CashClose;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps difference as counted minus expected on save', function () {
    $close = CashClose::factory()->create(['expected_cash' => 90_000, 'counted_cash' => 100_000]);
    expect($close->difference)->toBe(10_000)
        ->and($close->status)->toBe(CashCloseStatus::Open);
});
