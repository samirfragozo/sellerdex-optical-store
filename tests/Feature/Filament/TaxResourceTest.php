<?php

use App\Enums\TaxTreatment;
use App\Filament\Resources\Taxes\Pages\CreateTax;
use App\Filament\Resources\Taxes\Pages\ListTaxes;
use App\Filament\Resources\Taxes\TaxResource;
use App\Models\Tax;
use App\Models\User;
use Livewire\Livewire;

it('lets an admin list and create taxes', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Tax::seedDefaultsFor($admin->company);

    Livewire::test(ListTaxes::class)->assertCanSeeTableRecords(Tax::all());

    Livewire::test(CreateTax::class)
        ->fillForm(['name' => 'Impoconsumo 8%', 'rate' => 8, 'treatment' => TaxTreatment::Taxed->value, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Tax::where('name', 'Impoconsumo 8%')->sole()->is_system)->toBeFalse();
});

it('is not available to sellers', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get(TaxResource::getUrl('index'))
        ->assertForbidden();
});
