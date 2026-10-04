<?php

use App\Actions\RegisterSale;
use App\Models\LensCombination;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('snapshots the category warranty on a product line and keeps it when the category changes', function () {
    $category = ProductCategory::factory()->create(['warranty_months' => 6]);
    $product = Product::factory()->create(['product_category_id' => $category->id]);
    $sale = Sale::factory()->create();

    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);
    $category->update(['warranty_months' => 3]);

    expect($item->fresh()->warranty_months)->toBe(6)
        ->and($item->fresh()->warrantyMonths())->toBe(6);
});

it('falls back to the legal 12 months for a manual line', function () {
    $item = SaleItem::factory()->create(['sale_id' => Sale::factory()->create()->id, 'product_id' => null]);

    expect($item->warranty_months)->toBeNull()
        ->and($item->warrantyMonths())->toBe(SaleItem::LEGAL_WARRANTY_MONTHS);
});

it('seeds 6 months for lenses and frames', function () {
    $months = collect(ProductCategory::SYSTEM_CATEGORIES)->pluck('warranty_months', 'key');

    expect($months['lens'])->toBe(6)->and($months['frame'])->toBe(6);
});

it('prints each line warranty and the legal note on the sale document', function () {
    $category = ProductCategory::factory()->create(['warranty_months' => 6]);
    $sale = Sale::factory()->create();
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => Product::factory()->create(['product_category_id' => $category->id])->id,
        'description' => 'Montura acetato',
    ]);

    $this->get(route('documents.invoice', $sale))
        ->assertOk()
        ->assertSee(__('app.documents.warranty_term', ['months' => 6]))
        ->assertSee(__('app.documents.warranty_note'));
});

it('snapshots the lens category warranty on an armado lens line', function () {
    ProductCategory::factory()->create(['key' => 'lens', 'is_system' => true, 'warranty_months' => 6]);
    $combination = LensCombination::factory()->priced(180000, 60000)->create();

    $sale = (new RegisterSale)->handle([
        'document_type' => 'order',
        'armados' => [[
            'lens' => [
                'description' => 'Lente formulado',
                'quantity' => 1,
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
                'treatment_ids' => [],
            ],
            'own_frame' => true,
        ]],
    ], $this->admin);

    expect($sale->items->first(fn ($i) => $i->isLens())->warranty_months)->toBe(6);
});
