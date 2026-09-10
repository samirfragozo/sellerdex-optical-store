<?php

use App\Actions\GenerateProductVariants;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductCategory;

beforeEach(function () {
    $category = ProductCategory::factory()->create(['key' => 'frame']);
    $this->base = Product::factory()->create(['product_category_id' => $category->id, 'sku' => 'MNT-BASE', 'name' => 'Montura']);

    $structure = OptionGroup::factory()->create(['name' => 'Estructura']);
    $this->completas = Option::factory()->for($structure, 'group')->create(['name' => 'Completas']);
    $this->semiAlAire = Option::factory()->for($structure, 'group')->create(['name' => 'Semi Al Aire']);

    $material = OptionGroup::factory()->create(['name' => 'Material']);
    $this->acetato = Option::factory()->for($material, 'group')->create(['name' => 'Acetato']);
    $this->metal = Option::factory()->for($material, 'group')->create(['name' => 'Metal']);

    $this->base->optionGroups()->attach([$structure->id, $material->id]);
});

it('generates the cartesian of options as child products', function () {
    $result = app(GenerateProductVariants::class)->handle($this->base);

    expect($result)->toBe(['created' => 4, 'skipped' => 0])
        ->and($this->base->variants()->count())->toBe(4);

    $variant = $this->base->variants()
        ->whereHas('variantOptions', fn ($q) => $q->where('options.id', $this->completas->id))
        ->whereHas('variantOptions', fn ($q) => $q->where('options.id', $this->acetato->id))
        ->first();

    expect($variant)->not->toBeNull()
        ->and($variant->name)->toBe('Montura Completas Acetato')
        ->and($variant->sku)->toBe('MNT-BASE-COMPLETAS-ACETATO')
        ->and($variant->is_pos_selectable)->toBeFalse()
        ->and($variant->is_stockable)->toBeTrue()
        ->and($variant->stock)->toBe(0);
});

it('is idempotent: running it again skips existing combinations', function () {
    app(GenerateProductVariants::class)->handle($this->base);
    $result = app(GenerateProductVariants::class)->handle($this->base);

    expect($result)->toBe(['created' => 0, 'skipped' => 4])
        ->and($this->base->variants()->count())->toBe(4);
});

it('does nothing for a base with no required option groups', function () {
    $category = ProductCategory::factory()->create();
    $standalone = Product::factory()->create(['product_category_id' => $category->id]);

    $result = app(GenerateProductVariants::class)->handle($standalone);

    expect($result)->toBe(['created' => 0, 'skipped' => 0])
        ->and($standalone->variants()->count())->toBe(0);
});
