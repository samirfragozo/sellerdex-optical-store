<?php

use App\Enums\PrismBase;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the formula HTML with the Rx values', function () {
    $seller = User::factory()->seller()->create();
    $this->actingAs($seller);
    $rx = Prescription::factory()->create([
        'od_sphere' => '-0.25', 'od_cylinder' => '-2.00',
        'os_sphere' => '0', 'os_cylinder' => '-2.75', 'od_add' => '1.5',
    ]);

    $this->get(route('documents.formula', $rx))
        ->assertSuccessful()
        ->assertSee('-2.75')
        ->assertSee('-0.25')
        ->assertSee('+1.50');
});

it('prints prism, prescriber, expiry and notes on the formula', function () {
    $this->actingAs(User::factory()->seller()->create());
    $rx = Prescription::factory()->create([
        'exam_date' => '2026-03-10',
        'od_sphere' => '-1.25',
        'od_prism' => '1.50',
        'od_prism_base' => PrismBase::In,
        'od_pd' => '31.5',
        'os_pd' => '32',
        'prescriber_name' => 'Dra. Ana Gómez',
        'prescriber_license' => 'TP 12345',
        'notes' => 'Uso permanente',
        'attachment' => 'prescriptions/rx.jpg',
    ]);

    $this->get(route('documents.formula', $rx))
        ->assertSuccessful()
        ->assertSee('-1.25')
        ->assertSee('1.50')
        ->assertSee(__('app.prism_base.in'))
        ->assertSee('31.5')
        ->assertSee('32.0')
        ->assertSee('Dra. Ana Gómez')
        ->assertSee('TP 12345')
        ->assertSee($rx->expires_at->format('d/m/Y'))
        ->assertSee('Uso permanente')
        ->assertSee(route('documents.prescription.attachment', $rx))
        ->assertSee(__('app.documents.view_attachment'));
});

it('downloads the formula as a PDF', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $rx = Prescription::factory()->create();

    $response = $this->get(route('documents.formula.pdf', $rx));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('redirects guests away from the formula', function () {
    $this->get(route('documents.formula', Prescription::factory()->create()))->assertRedirect();
});
