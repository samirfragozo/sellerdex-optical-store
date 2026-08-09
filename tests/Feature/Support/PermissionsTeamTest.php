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
