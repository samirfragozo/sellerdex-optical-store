<?php

use App\Models\LensCombination;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\User;

function readyAdmin(): User
{
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin);

    PaymentMethod::factory()->create(['company_id' => $admin->company_id, 'is_active' => true]);
    Supplier::factory()->create(['company_id' => $admin->company_id, 'is_laboratory' => true, 'is_active' => true, 'lead_time_days' => 3]);
    LensCombination::factory()->priced(100000)->create(['company_id' => $admin->company_id, 'is_active' => true]);

    return $admin;
}

it('shows no readiness UI for a fully configured company', function () {
    $this->actingAs(readyAdmin())->get('/admin')
        ->assertSuccessful()
        ->assertDontSee(__('app.readiness.laboratory_lead_time'))
        ->assertDontSee(__('app.readiness.blocking_title'));
});

it('shows warnings as a banner with a link to fix them', function () {
    $admin = readyAdmin();
    Supplier::withoutGlobalScopes()->where('company_id', $admin->company_id)->update(['lead_time_days' => null]);

    $this->actingAs($admin)->get('/admin')
        ->assertSee(__('app.readiness.laboratory_lead_time'))
        ->assertSee(__('app.readiness.fix'))
        ->assertDontSee(__('app.readiness.blocking_title'));
});

it('shows global blockers in a modal on the dashboard', function () {
    $admin = readyAdmin();
    PaymentMethod::withoutGlobalScopes()->where('company_id', $admin->company_id)->update(['is_active' => false]);

    $this->actingAs($admin)->get('/admin')
        ->assertSee(__('app.readiness.blocking_title'))
        ->assertSee(__('app.readiness.payment_method'));
});

it('asks a seller to contact the admin instead of linking to pages they cannot open', function () {
    $admin = readyAdmin();
    Supplier::withoutGlobalScopes()->where('company_id', $admin->company_id)->update(['lead_time_days' => null]);
    $seller = User::factory()->forCompany($admin->company)->seller()->create();

    $this->actingAs($seller)->get('/admin')
        ->assertSuccessful()
        ->assertSee(__('app.readiness.laboratory_lead_time'))
        ->assertSee(__('app.readiness.ask_admin'))
        ->assertDontSee(__('app.readiness.fix'));
});
