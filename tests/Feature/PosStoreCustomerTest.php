<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a customer immediately from the pos modal', function () {
    $seller = User::factory()->seller()->create();

    $response = $this->actingAs($seller)->postJson('/pos/customers', [
        'name' => 'Ana',
        'last_name' => 'Gómez',
        'document_type' => 'cc',
        'id_number' => '123456789',
        'phone' => '3001234567',
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Ana')
        ->assertJsonPath('last_name', 'Gómez')
        ->assertJsonPath('id_number', '123456789');

    $customer = Customer::where('id_number', '123456789')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->company_id)->toBe($seller->company_id);
});

it('validates required fields when creating a customer from the pos modal', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)->postJson('/pos/customers', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'last_name', 'document_type', 'id_number', 'phone']);
});

it('requires authentication to create a customer from the pos modal', function () {
    $this->postJson('/pos/customers', [])->assertUnauthorized();
});

it('searches customers by name, last name, or id number', function () {
    $seller = User::factory()->seller()->create();
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '111']);
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Carlos', 'last_name' => 'Ana', 'id_number' => '222']);
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Luis', 'last_name' => 'Pérez', 'id_number' => '333']);

    $response = $this->actingAs($seller)->getJson('/pos/customers/search?q=ana');

    $response->assertOk();
    expect($response->json())->toHaveCount(2)
        ->and(collect($response->json())->pluck('name')->sort()->values()->all())->toBe(['Ana', 'Carlos']);
});

it('requires authentication to search customers from the pos modal', function () {
    $this->getJson('/pos/customers/search?q=ana')->assertUnauthorized();
});
