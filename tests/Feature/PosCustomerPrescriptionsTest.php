<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\User;
use App\Support\PermissionsTeam;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
});

it('lists every prescription of the customer, newest exam first, as POS options', function () {
    $customer = Customer::factory()->create();
    $old = Prescription::factory()->create(['customer_id' => $customer->id, 'exam_date' => now()->subYear()->toDateString()]);
    $new = Prescription::factory()->create(['customer_id' => $customer->id, 'exam_date' => now()->subWeek()->toDateString()]);
    Prescription::factory()->create(); // another customer's

    $this->getJson(route('pos.customers.prescriptions', $customer))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', $new->id)
        ->assertJsonPath('1.id', $old->id)
        ->assertJsonPath('1.customer_id', $customer->id)
        ->assertJsonStructure([['id', 'customer_id', 'exam_date', 'expires_at', 'is_expired', 'summary']]);
});

it('offers a returning patient prescriptions older than the 200 most recent of the shop', function () {
    $patient = Customer::factory()->create();
    $oldest = Prescription::factory()->create(['customer_id' => $patient->id, 'exam_date' => now()->subMonths(23)->toDateString()]);
    Prescription::factory()->count(200)->create(['exam_date' => now()->subDay()->toDateString()]);

    $this->getJson(route('pos.customers.prescriptions', $patient))
        ->assertOk()
        ->assertJsonPath('0.id', $oldest->id);
});

it('does not list prescriptions of another company customer', function () {
    $foreign = Customer::factory()->for(Company::factory()->create())->create();

    $this->getJson(route('pos.customers.prescriptions', $foreign->id))->assertNotFound();
});

it('forbids a user who cannot view prescriptions', function () {
    $customer = Customer::factory()->create();
    $role = Role::where('company_id', $this->seller->company_id)->where('name', 'seller')->firstOrFail();
    PermissionsTeam::runAs($this->seller->company, fn () => $role->revokePermissionTo('ViewAny:Prescription'));

    $this->getJson(route('pos.customers.prescriptions', $customer))->assertForbidden();
});
