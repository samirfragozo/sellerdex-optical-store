<?php

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the formula HTML with the Rx values', function () {
    $rx = Prescription::factory()->create([
        'od_sphere' => '-0.25', 'od_cylinder' => '-2.00',
        'os_sphere' => 'N', 'os_cylinder' => '-2.75',
    ]);

    $this->actingAs(User::factory()->seller()->create())
        ->get(route('documents.formula', $rx))
        ->assertSuccessful()
        ->assertSee('-2.75')
        ->assertSee('-0.25');
});

it('downloads the formula as a PDF', function () {
    $rx = Prescription::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('documents.formula.pdf', $rx));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('redirects guests away from the formula', function () {
    $this->get(route('documents.formula', Prescription::factory()->create()))->assertRedirect();
});
