<?php

use App\Models\CashRegisterSession;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('turns the page and filters the pos product catalog', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);
    Product::factory()->count(25)->create([
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
    ]);
    $this->actingAs($seller);

    $page = visit('/pos');
    $page->assertSee('1–20 de 25')
        ->click('text="2"')
        ->wait(1)
        ->assertSee('21–25 de 25')
        ->assertNoJavaScriptErrors();
});

it('excludes products with option groups from the pos catalog grid', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);

    $plainProduct = Product::factory()->create([
        'name' => 'Plain Frame',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
    ]);
    $productWithOptions = Product::factory()->create([
        'name' => 'Frame With Color Option',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
    ]);
    $optionGroup = OptionGroup::factory()->create([
        'company_id' => $seller->company_id,
        'is_active' => true,
    ]);
    $productWithOptions->optionGroups()->attach($optionGroup);

    $this->actingAs($seller);

    $page = visit('/pos');
    $page->assertSee('Plain Frame')
        ->assertDontSee('Frame With Color Option')
        ->assertNoJavaScriptErrors();
});
