<?php

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemOption;

it('snapshots the chosen option name/price/cost on a sale item', function () {
    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->for($sale)->create();
    $group = OptionGroup::factory()->create(['name' => 'Filtro']);
    $option = Option::factory()->for($group, 'group')->create(['name' => 'Blue Cut', 'price' => 70000, 'cost' => 20000]);

    $item->options()->create([
        'option_group_id' => $group->id,
        'option_id' => $option->id,
        'option_group_name' => $group->name,
        'option_name' => $option->name,
        'price' => $option->price,
        'cost' => $option->cost,
    ]);

    expect($item->options)->toHaveCount(1)
        ->and($item->options->first()->option_name)->toBe('Blue Cut');

    // Snapshot survives the option being deleted.
    $option->delete();
    $item->refresh();
    expect($item->options->first()->option_name)->toBe('Blue Cut')
        ->and($item->options->first()->option_id)->toBeNull();
});
