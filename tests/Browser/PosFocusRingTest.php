<?php

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gives the catalog category chip and product card a visible focus-ring class', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id, 'name' => 'Monturas']);
    Product::factory()->create([
        'name' => 'Gafas de prueba',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
        'price' => 50_000,
    ]);
    $this->actingAs($seller);

    $page = visit('/pos');

    $categoryChipHasRing = $page->script(
        "[...document.querySelectorAll('button')].some(b => b.textContent.trim() === 'Monturas' && b.className.includes('focus-visible:ring'))"
    );
    $productCardHasRing = $page->script(
        "[...document.querySelectorAll('button')].some(b => b.textContent.includes('Gafas de prueba') && b.className.includes('focus-visible:ring'))"
    );

    expect($categoryChipHasRing)->toBeTrue()
        ->and($productCardHasRing)->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

it('gives the checkout remove-payment button a visible focus-ring class', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id, 'name' => 'Monturas']);
    Product::factory()->create([
        'name' => 'Gafas de prueba',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
        'price' => 50_000,
    ]);
    $this->actingAs($seller);

    $page = visit('/pos');
    $page->click('text=Gafas de prueba')
        ->click('text=Cobrar')
        ->click('text=Agregar método de pago');

    $removePaymentButtonHasRing = $page->script(
        "[...document.querySelectorAll('button')].some(b => b.querySelector('svg') && b.className.includes('focus-visible:ring') && b.className.includes('size-8'))"
    );

    expect($removePaymentButtonHasRing)->toBeTrue();

    $page->assertNoJavaScriptErrors();
});
