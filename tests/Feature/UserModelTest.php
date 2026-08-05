<?php

use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('a fresh user has no business activity', function () {
    $user = User::factory()->create();

    expect($user->hasBusinessActivity())->toBeFalse();
});

it('a user who sold a sale has business activity', function () {
    $user = User::factory()->create();
    Sale::factory()->create(['company_id' => $user->company_id, 'seller_id' => $user->id]);

    expect($user->hasBusinessActivity())->toBeTrue();
});

it('a user who received a payment has business activity', function () {
    $user = User::factory()->create();
    Payment::factory()->create(['company_id' => $user->company_id, 'received_by' => $user->id]);

    expect($user->hasBusinessActivity())->toBeTrue();
});

it('the auth model has no global scopes', function () {
    // Regression guard: User is the app's Auth model. A global scope that reads
    // Auth::user() (e.g. BelongsToCompany's CompanyScope) recurses infinitely,
    // because resolving Auth::user() itself queries User. Company scoping for
    // users must stay in UserResource::getEloquentQuery(), not on the model.
    expect((new User)->getGlobalScopes())->toBeEmpty();
});
