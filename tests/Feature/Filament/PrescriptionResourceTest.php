<?php

use App\Enums\PrismBase;
use App\Filament\Resources\Prescriptions\Pages\CreatePrescription;
use App\Filament\Resources\Prescriptions\Pages\EditPrescription;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('flags expired prescriptions in the list', function () {
    $this->actingAs(User::factory()->admin()->create());
    Prescription::factory()->create(['exam_date' => now()->subMonths(13)->toDateString()]);

    $this->get('/admin/prescriptions')->assertSee(__('app.documents.expired'));
});

it('does not flag current prescriptions as expired', function () {
    $this->actingAs(User::factory()->admin()->create());
    Prescription::factory()->create(['exam_date' => now()->subMonth()->toDateString()]);

    $this->get('/admin/prescriptions')->assertDontSee(__('app.documents.expired'));
});

it('combina el signo y el valor al crear una prescripción', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);
    $customer = Customer::factory()->create();

    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'exam_date' => now()->subMonth()->toDateString(),
            'prescriber_name' => 'Dra. Ana Gómez',
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
        ->assertHasFormErrors(['od_sphere_num', 'od_axis', 'prescriber_name' => 'required']);
});

it('splits and recombines the stored decimal diopters when editing', function () {
    $this->actingAs(User::factory()->admin()->create());
    $prescription = Prescription::factory()->create(['od_sphere' => '-2.25', 'os_add' => '1.00']);

    Livewire::test(EditPrescription::class, ['record' => $prescription->getRouteKey()])
        ->assertFormFieldIsDisabled('expires_at')
        ->assertSchemaStateSet([
            'expires_at' => $prescription->expires_at->toDateString(),
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

it('stores the uploaded prescription privately along with prism and prescriber', function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $customer = Customer::factory()->create();

    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'exam_date' => now()->subMonth()->toDateString(),
            'prescriber_name' => 'Dra. Ana Gómez',
            'prescriber_license' => 'TP 12345',
            'od_prism' => '1.50',
            'od_prism_base' => PrismBase::In->value,
            'notes' => 'Uso permanente',
            'attachment' => UploadedFile::fake()->image('rx.jpg'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prescription = Prescription::sole();
    expect($prescription->attachment)->toStartWith('prescriptions/')
        ->and($prescription->prescriber_license)->toBe('TP 12345')
        ->and($prescription->od_prism)->toBe('1.50')
        ->and($prescription->od_prism_base)->toBe(PrismBase::In)
        ->and($prescription->notes)->toBe('Uso permanente');
    Storage::disk('local')->assertExists($prescription->attachment);
    Storage::disk('public')->assertMissing($prescription->attachment);
});

it('requires the prism base when a prism is given', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'customer_id' => Customer::factory()->create()->id,
            'exam_date' => now()->subMonth()->toDateString(),
            'prescriber_name' => 'Dra. Ana Gómez',
            'od_prism' => '1.30',
        ])
        ->call('create')
        ->assertHasFormErrors(['od_prism', 'od_prism_base']);
});

it('renders translated labels on the prescription form and list', function () {
    $this->actingAs(User::factory()->admin()->create());
    Prescription::factory()->create();

    $this->get('/admin/prescriptions/create')
        ->assertSuccessful()
        ->assertSee(__('app.fields.prescriber_name'))
        ->assertSee(__('app.fields.attachment'))
        ->assertDontSee('app.fields.');

    $this->get('/admin/prescriptions')
        ->assertSee(__('app.fields.expires_at'))
        ->assertDontSee('app.fields.');
});

it('rejects an SVG attachment', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreatePrescription::class)
        ->fillForm([
            'customer_id' => Customer::factory()->create()->id,
            'exam_date' => now()->subMonth()->toDateString(),
            'prescriber_name' => 'Dra. Ana Gómez',
            'attachment' => UploadedFile::fake()->createWithContent('rx.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])
        ->call('create')
        ->assertHasFormErrors(['attachment']);

    expect(Prescription::count())->toBe(0);
});
