<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->customer = Customer::factory()->create();

    $lensCategory = ProductCategory::factory()->create(['key' => 'lens', 'generates_lab_order' => true]);
    $this->lens = Product::factory()->create(['product_category_id' => $lensCategory->id, 'price' => 0, 'cost' => 0, 'is_stockable' => false]);

    $material = OptionGroup::factory()->create(['name' => 'Material']);
    $this->materialOption = Option::factory()->for($material, 'group')->create(['name' => 'Policarbonato', 'price' => 30000, 'cost' => 10000]);
    $filter = OptionGroup::factory()->create(['name' => 'Filtro']);
    $this->filterOption = Option::factory()->for($filter, 'group')->create(['name' => 'Blue Cut', 'price' => 70000, 'cost' => 20000]);
    $this->lens->optionGroups()->attach([$material->id => ['sort_order' => 1], $filter->id => ['sort_order' => 2]]);
});

it('prices an armado lens from its chosen options and snapshots them', function () {
    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => [
                'product_id' => $this->lens->id,
                'description' => $this->lens->name,
                'unit_price' => 1, // deliberately wrong — the server must ignore this
                'option_ids' => [$this->materialOption->id, $this->filterOption->id],
            ],
        ]],
    ], $this->seller);

    $lensItem = $sale->items->firstWhere('product_id', $this->lens->id);

    expect($lensItem->unit_price)->toBe(100000)
        ->and($lensItem->unit_cost)->toBe(30000)
        ->and($lensItem->options)->toHaveCount(2)
        ->and($lensItem->options->pluck('option_name')->all())->toBe(['Policarbonato', 'Blue Cut']);
});
