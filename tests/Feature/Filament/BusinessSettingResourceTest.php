<?php

use App\Filament\Resources\BusinessSettings\BusinessSettingResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an admin open the single business-settings record', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(BusinessSettingResource::getUrl())
        ->assertSuccessful();
});

it('forbids a seller from the business settings', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get(BusinessSettingResource::getUrl())
        ->assertForbidden();
});
