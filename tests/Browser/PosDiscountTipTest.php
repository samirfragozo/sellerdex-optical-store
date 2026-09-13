<?php

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows discount, tip and tax in the cart summary', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);
    Product::factory()->create([
        'name' => 'Gafas de sol',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
        'price' => 100_000,
        'tax_rate' => 19,
    ]);
    $this->actingAs($seller);

    $page = visit('/pos');
    $page->click('text=Gafas de sol')
        ->fill('[data-testid="discount-percent-input"]', '10')
        ->fill('[data-testid="tip-percent-input"]', '5')
        ->assertSee('$17.100') // tax on 90_000 base at 19%
        ->assertSee('$4.500')  // tip
        ->assertNoJavaScriptErrors();
});
