<?php

use App\Models\CashRegisterSession;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tax;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies the discount to the tax-inclusive price in the cart summary', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'is_active' => true]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);
    Product::factory()->create([
        'name' => 'Gafas de sol',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
        'price' => 100_000,
        'tax_id' => Tax::factory()->create(['company_id' => $seller->company_id, 'rate' => 19])->id,
    ]);
    $this->actingAs($seller);

    $page = visit('/pos');
    $page->click('text=Gafas de sol')
        ->fill('[data-testid="discount-percent-input"]', '10')
        ->assertSee('$90.000')
        ->assertNoJavaScriptErrors();
});
