<?php

use App\Filament\Pages\Onboarding;
use App\Models\Company;
use App\Models\User;

it('sends the admin of a new company to the onboarding from the panel and the POS', function () {
    $admin = User::factory()->forCompany(Company::factory()->notOnboarded()->create())->admin()->create();

    $this->actingAs($admin)->get('/admin')->assertRedirect(Onboarding::getUrl());
    $this->actingAs($admin)->get(route('pos.index'))->assertRedirect(Onboarding::getUrl());
});

it('lets the admin open the onboarding itself', function () {
    $admin = User::factory()->forCompany(Company::factory()->notOnboarded()->create())->admin()->create();

    $this->actingAs($admin)->get(Onboarding::getUrl())->assertSuccessful();
});

it('tells staff of a new company to wait instead of looping', function () {
    $seller = User::factory()->forCompany(Company::factory()->notOnboarded()->create())->seller()->create();

    $this->actingAs($seller)->get(route('pos.index'))
        ->assertForbidden()
        ->assertSee(__('app.onboarding.pending_for_staff'));
    $this->actingAs($seller)->get('/admin')->assertForbidden();
});

it('lets onboarded companies through', function () {
    $admin = User::factory()->admin()->create();
    openCashRegisterSession($admin);

    $this->actingAs($admin)->get('/admin')->assertSuccessful();
    $this->actingAs($admin)->get(route('pos.index'))->assertSuccessful();
});
