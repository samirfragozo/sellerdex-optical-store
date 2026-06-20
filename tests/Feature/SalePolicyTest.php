<?php

use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Policies\PaymentPolicy;
use App\Policies\SalePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets sellers create and update sales but not delete or void', function () {
    $policy = new SalePolicy;
    $seller = User::factory()->seller()->create();
    $sale = Sale::factory()->create();

    expect($policy->create($seller))->toBeTrue()
        ->and($policy->update($seller, $sale))->toBeTrue()
        ->and($policy->delete($seller, $sale))->toBeFalse()
        ->and($policy->void($seller, $sale))->toBeFalse();
});

it('lets admins void and delete sales', function () {
    $policy = new SalePolicy;
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->create();

    expect($policy->void($admin, $sale))->toBeTrue()
        ->and($policy->delete($admin, $sale))->toBeTrue();
});

it('lets sellers register payments but not edit or delete registered ones', function () {
    $policy = new PaymentPolicy;
    $seller = User::factory()->seller()->create();
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->create();

    expect($policy->create($seller))->toBeTrue()
        ->and($policy->update($seller, $payment))->toBeFalse()
        ->and($policy->delete($seller, $payment))->toBeFalse()
        ->and($policy->update($admin, $payment))->toBeTrue()
        ->and($policy->delete($admin, $payment))->toBeTrue();
});
