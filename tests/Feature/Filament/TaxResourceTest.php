<?php

use App\Enums\TaxTreatment;
use App\Filament\Resources\Taxes\Pages\CreateTax;
use App\Filament\Resources\Taxes\Pages\EditTax;
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

it('forces a zero rate on an excluded tax', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateTax::class)
        ->fillForm(['name' => 'Excluido', 'treatment' => TaxTreatment::Excluded->value, 'rate' => 19])
        ->call('create')
        ->assertHasNoFormErrors();

    $tax = Tax::where('name', 'Excluido')->sole();
    expect((float) $tax->rate)->toBe(0.0);

    Livewire::test(EditTax::class, ['record' => $tax->getRouteKey()])->assertFormFieldIsDisabled('rate');
});

it('requires a positive rate on a taxed tax', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateTax::class)
        ->fillForm(['name' => 'IVA cero', 'treatment' => TaxTreatment::Taxed->value, 'rate' => 0])
        ->call('create')
        ->assertHasFormErrors(['rate']);

    expect(Tax::where('name', 'IVA cero')->exists())->toBeFalse();
});
