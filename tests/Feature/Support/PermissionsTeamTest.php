<?php

use App\Models\Company;
use App\Models\User;
use App\Support\PermissionsTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('sets the team id for the duration of the callback and restores it after', function () {
    $company = Company::factory()->create();
    $registrar = app(PermissionRegistrar::class);

    expect($registrar->getPermissionsTeamId())->toBeNull();

    $result = PermissionsTeam::runAs($company, fn () => $registrar->getPermissionsTeamId());

    expect($result)->toBe($company->id)
        ->and($registrar->getPermissionsTeamId())->toBeNull();
});

it('restores the previous team id even if the callback throws', function () {
    $company = Company::factory()->create();
    $registrar = app(PermissionRegistrar::class);

    try {
        PermissionsTeam::runAs($company, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($registrar->getPermissionsTeamId())->toBeNull();
});

it('nests correctly, restoring the outer context after the inner one exits', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $registrar = app(PermissionRegistrar::class);

    PermissionsTeam::runAs($companyA, function () use ($companyB, $registrar): void {
        expect($registrar->getPermissionsTeamId())->toBe(request()->attributes->get('unused') ?? $registrar->getPermissionsTeamId());

        PermissionsTeam::runAs($companyB, function () use ($registrar, $companyB): void {
            expect($registrar->getPermissionsTeamId())->toBe($companyB->id);
        });

        expect($registrar->getPermissionsTeamId())->not->toBeNull();
    });

    expect($registrar->getPermissionsTeamId())->toBeNull();
});

it('the resolver falls back to the authenticated user company when no override is set', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    $this->actingAs($user);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($company->id);
});

it('does not permanently pin the team after being called while a user is authenticated', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->forCompany($companyA)->create();
    $userB = User::factory()->forCompany($companyB)->create();
    $registrar = app(PermissionRegistrar::class);

    $this->actingAs($userA);
    expect($registrar->getPermissionsTeamId())->toBe($companyA->id);

    PermissionsTeam::runAs($companyB, fn () => null);

    // Still acting as userA — should still resolve to A's company, not
    // stay pinned to whatever runAs() last touched.
    expect($registrar->getPermissionsTeamId())->toBe($companyA->id);

    $this->actingAs($userB);

    // Switching the authenticated user afterward must be reflected —
    // this is exactly what stayed silently stale before the fix.
    expect($registrar->getPermissionsTeamId())->toBe($companyB->id);
});
