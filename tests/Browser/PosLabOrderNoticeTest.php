<?php

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not show the lab order notice after selling a plain product', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);
    Product::factory()->create([
        'name' => 'Estuche rígido',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
        'price' => 15_000,
    ]);
    $this->actingAs($seller);

    $page = visit('/pos');
    $page->click('.line-clamp-2')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertNoJavaScriptErrors()
        ->assertSee('creada exitosamente')
        ->assertDontSee('orden de laboratorio pendiente');
});
