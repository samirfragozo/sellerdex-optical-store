<?php

use App\Filament\Resources\Prescriptions\Pages\CreatePrescription;
use App\Filament\Resources\Prescriptions\Pages\EditPrescription;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('el admin ve el listado de prescripciones', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/prescriptions')
        ->assertSuccessful();
});

it('el vendedor también puede ver el listado de prescripciones', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get('/admin/prescriptions')
        ->assertSuccessful();
});

it('combina el signo y el valor al crear una prescripción', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);
    $customer = Customer::factory()->create();

    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'exam_date' => now()->subMonth()->toDateString(),
            'od_sphere_sign' => '-',
            'od_sphere_num' => '2.25',
            'os_add_num' => '1.00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prescription = Prescription::first();
    expect($prescription->od_sphere)->toBe('-2.25')
        ->and($prescription->os_add)->toBe('1.00');
});

it('valida el rango y el paso de los dioptrías en Filament', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'exam_date' => now()->subMonth()->toDateString(),
            'od_sphere_num' => '2.30', // not a multiple of 0.25
            'od_cylinder_num' => '1.00', // cylinder without axis
        ])
        ->call('create')
        ->assertHasFormErrors(['od_sphere_num', 'od_axis']);
});

it('splits and recombines the stored decimal diopters when editing', function () {
    $this->actingAs(User::factory()->admin()->create());
    $prescription = Prescription::factory()->create(['od_sphere' => '-2.25', 'os_add' => '1.00']);

    Livewire::test(EditPrescription::class, ['record' => $prescription->getRouteKey()])
        ->assertSchemaStateSet([
            'od_sphere_sign' => '-',
            'od_sphere_num' => '2.25',
            'os_add_num' => '1.00',
        ])
        ->fillForm(['od_sphere_sign' => '+'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($prescription->fresh()->od_sphere)->toBe('2.25')
        ->and($prescription->fresh()->os_add)->toBe('1.00');
});
