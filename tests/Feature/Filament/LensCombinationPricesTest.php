<?php

use App\Filament\Resources\LensCombinations\Pages\EditLensCombination;
use App\Filament\Resources\LensCombinations\RelationManagers\PricesRelationManager;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->lab = Supplier::factory()->laboratory()->create(['name' => 'Lab Central']);
    $this->combination = LensCombination::factory()->unpriced()->create();
});

function pricesManager()
{
    return Livewire::test(PricesRelationManager::class, [
        'ownerRecord' => test()->combination,
        'pageClass' => EditLensCombination::class,
    ]);
}

it('adds a price range for a lab', function () {
    pricesManager()
        ->callAction(TestAction::make('create')->table(), [
            'supplier_id' => $this->lab->id,
            'sphere_min' => -20, 'sphere_max' => -6.25, 'cylinder_min' => -10, 'cylinder_max' => 10,
            'cost' => 90_000, 'price' => 280_000, 'is_preferred' => true, 'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    expect($this->combination->prices()->sole())
        ->supplier_id->toBe($this->lab->id)
        ->price->toBe(280_000)
        ->sphere_max->toBe('-6.25');
});

it('rejects a range whose maximum is below its minimum', function () {
    pricesManager()
        ->callAction(TestAction::make('create')->table(), [
            'supplier_id' => $this->lab->id,
            'sphere_min' => 2, 'sphere_max' => -2, 'cylinder_min' => -10, 'cylinder_max' => 10,
            'cost' => 1, 'price' => 1,
        ])
        ->assertHasFormErrors(['sphere_max']);
});

it('rejects an addition range whose maximum is below its minimum', function () {
    pricesManager()
        ->callAction(TestAction::make('create')->table(), [
            'supplier_id' => $this->lab->id,
            'sphere_min' => -20, 'sphere_max' => 20, 'cylinder_min' => -10, 'cylinder_max' => 10,
            'add_min' => 2, 'add_max' => 1,
            'cost' => 1, 'price' => 1,
        ])
        ->assertHasFormErrors(['add_max']);
});

it('accepts an open addition range', function () {
    pricesManager()
        ->callAction(TestAction::make('create')->table(), [
            'supplier_id' => $this->lab->id,
            'sphere_min' => -20, 'sphere_max' => 20, 'cylinder_min' => -10, 'cylinder_max' => 10,
            'cost' => 1, 'price' => 1,
        ])
        ->assertHasNoFormErrors();

    expect($this->combination->prices()->sole())->add_min->toBeNull()->add_max->toBeNull();
});

it('only offers active labs of the company', function () {
    $notLab = Supplier::factory()->create(['name' => 'Distribuidora']);
    $inactiveLab = Supplier::factory()->laboratory()->create(['name' => 'Lab Cerrado', 'is_active' => false]);

    foreach ([$notLab, $inactiveLab] as $supplier) {
        pricesManager()
            ->callAction(TestAction::make('create')->table(), [
                'supplier_id' => $supplier->id,
                'sphere_min' => -20, 'sphere_max' => 20, 'cylinder_min' => -10, 'cylinder_max' => 10,
                'cost' => 1, 'price' => 1,
            ])
            ->assertHasFormErrors(['supplier_id']);
    }
});

it('lists the lab and price of each range', function () {
    $this->combination->prices()->create([
        'supplier_id' => $this->lab->id, ...LensCombinationPrice::ALL_PRESCRIPTIONS,
        'cost' => 50_000, 'price' => 150_000, 'is_preferred' => true, 'is_active' => true,
    ]);

    pricesManager()->assertSee('Lab Central')->assertCanSeeTableRecords($this->combination->prices);
});
