<?php

use App\Actions\ResolveProductOptions;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->product = Product::factory()->create(['price' => 0, 'cost' => 0]);
    $this->material = OptionGroup::factory()->create(['name' => 'Material', 'is_required' => true]);
    $this->materialOption = Option::factory()->for($this->material, 'group')->create(['name' => 'Policarbonato', 'price' => 30000, 'cost' => 10000]);
    $this->filter = OptionGroup::factory()->create(['name' => 'Filtro', 'is_required' => true]);
    $this->filterOption = Option::factory()->for($this->filter, 'group')->create(['name' => 'Blue Cut', 'price' => 70000, 'cost' => 20000]);

    $this->product->optionGroups()->attach([$this->material->id => ['sort_order' => 1], $this->filter->id => ['sort_order' => 2]]);
});

it('sums price and cost deltas across the chosen options', function () {
    $result = app(ResolveProductOptions::class)->handle($this->product, [$this->materialOption->id, $this->filterOption->id]);

    expect($result['price'])->toBe(100000)
        ->and($result['cost'])->toBe(30000)
        ->and($result['options']->pluck('name')->all())->toBe(['Policarbonato', 'Blue Cut']);
});

it('rejects a selection missing a required group', function () {
    app(ResolveProductOptions::class)->handle($this->product, [$this->materialOption->id]);
})->throws(ValidationException::class);

it('rejects an option that does not belong to the product', function () {
    $foreignGroup = OptionGroup::factory()->create();
    $foreignOption = Option::factory()->for($foreignGroup, 'group')->create();

    app(ResolveProductOptions::class)->handle($this->product, [$this->materialOption->id, $this->filterOption->id, $foreignOption->id]);
})->throws(ValidationException::class);

it('rejects an inactive option', function () {
    $this->filterOption->update(['is_active' => false]);

    app(ResolveProductOptions::class)->handle($this->product, [$this->materialOption->id, $this->filterOption->id]);
})->throws(ValidationException::class);
