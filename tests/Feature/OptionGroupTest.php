<?php

use App\Models\Company;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;

it('scopes option groups to the current company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    OptionGroup::factory()->for($companyA, 'company')->create(['name' => 'Material A']);
    OptionGroup::factory()->for($companyB, 'company')->create(['name' => 'Material B']);

    $this->actingAs(\App\Models\User::factory()->forCompany($companyA)->create());

    expect(OptionGroup::pluck('name')->all())->toBe(['Material A']);
});

it('relates options to their group with price and cost', function () {
    $group = OptionGroup::factory()->create(['name' => 'Filtro']);
    Option::factory()->for($group, 'group')->create(['name' => 'Blue Cut', 'price' => 70000, 'cost' => 20000]);

    expect($group->options)->toHaveCount(1)
        ->and($group->options->first()->price)->toBe(70000)
        ->and($group->options->first()->cost)->toBe(20000);
});

it('attaches option groups to a product with a display order', function () {
    $product = Product::factory()->create();
    $material = OptionGroup::factory()->create(['name' => 'Material']);
    $filter = OptionGroup::factory()->create(['name' => 'Filtro']);

    $product->optionGroups()->attach([
        $material->id => ['sort_order' => 1],
        $filter->id => ['sort_order' => 2],
    ]);

    expect($product->optionGroups()->orderByPivot('sort_order')->pluck('name')->all())
        ->toBe(['Material', 'Filtro']);
});
