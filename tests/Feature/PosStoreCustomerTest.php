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
