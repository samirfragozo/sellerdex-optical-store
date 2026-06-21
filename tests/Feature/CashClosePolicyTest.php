<?php

use App\Models\CashClose;
use App\Models\User;
use App\Policies\CashClosePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('denies sellers from viewing any cash close', function () {
    $policy = new CashClosePolicy;
    $seller = User::factory()->seller()->create();

    expect($policy->viewAny($seller))->toBeFalse();
});

it('allows admins to view any cash close', function () {
    $policy = new CashClosePolicy;
    $admin = User::factory()->admin()->create();

    expect($policy->viewAny($admin))->toBeTrue();
});

it('allows admins to create cash closes', function () {
    $policy = new CashClosePolicy;
    $admin = User::factory()->admin()->create();

    expect($policy->create($admin))->toBeTrue();
});

it('allows admins to delete cash closes', function () {
    $policy = new CashClosePolicy;
    $admin = User::factory()->admin()->create();
    $cashClose = CashClose::factory()->create();

    expect($policy->delete($admin, $cashClose))->toBeTrue();
});
