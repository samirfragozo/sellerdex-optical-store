<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('generates variant products from the base product edit page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $category = ProductCategory::factory()->create(['key' => 'frame']);
    $base = Product::factory()->create(['product_category_id' => $category->id, 'sku' => 'MNT-BASE']);

    $structure = OptionGroup::factory()->create(['name' => 'Estructura']);
    Option::factory()->for($structure, 'group')->create(['name' => 'Completas']);
    Option::factory()->for($structure, 'group')->create(['name' => 'Tres Piezas']);
    $base->optionGroups()->attach($structure->id);

    Livewire::test(EditProduct::class, ['record' => $base->getRouteKey()])
        ->callAction('generateVariants');

    expect($base->variants()->count())->toBe(2);
});

it('hides the action for a product with no required option groups', function () {
    $this->actingAs(User::factory()->admin()->create());

    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertActionHidden('generateVariants');
});
