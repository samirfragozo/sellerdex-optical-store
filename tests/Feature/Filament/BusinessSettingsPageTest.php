<?php

use App\Filament\Pages\BusinessSettingsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an admin open the business settings page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(BusinessSettingsPage::getUrl())
        ->assertSuccessful();
});

it('forbids a seller from the business settings page', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get(BusinessSettingsPage::getUrl())
        ->assertForbidden();
});
