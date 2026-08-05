<?php

use App\Models\Payment;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets admins manage users but not sellers', function () {
    $policy = new UserPolicy;
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->seller()->create();
    $otherUser = User::factory()->seller()->create();

    expect($policy->create($admin))->toBeTrue()
        ->and($policy->update($admin, $otherUser))->toBeTrue()
        ->and($policy->create($seller))->toBeFalse()
        ->and($policy->update($seller, $otherUser))->toBeFalse();
});

it('lets an admin delete a user with no business activity', function () {
    $policy = new UserPolicy;
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->seller()->create();

    expect($policy->delete($admin, $otherUser))->toBeTrue();
});

it('blocks deleting a user with business activity', function () {
    $policy = new UserPolicy;
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->seller()->create();
    Payment::factory()->create(['received_by' => $seller->id]);

    expect($policy->delete($admin, $seller))->toBeFalse();
});

it('blocks an admin from deleting themselves', function () {
    $policy = new UserPolicy;
    $admin = User::factory()->admin()->create();

    expect($policy->delete($admin, $admin))->toBeFalse();
});
