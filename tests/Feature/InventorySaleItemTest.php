<?php

use App\Enums\SaleDocumentType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stockable(int $stock = 10): Product
{
    $category = ProductCategory::factory()->create();

    return Product::factory()->create([
        'product_category_id' => $category->id,
        'is_stockable' => true,
        'stock' => $stock,
    ]);
}

it('decrements stock when a stockable item is added to a real sale', function () {
    $product = stockable(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Order->value]);

    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 3]);

    expect($product->fresh()->stock)->toBe(7);
});

it('does not move stock for non-stockable products (lenses)', function () {
    $category = ProductCategory::factory()->create();
    $lens = Product::factory()->create(['product_category_id' => $category->id, 'is_stockable' => false, 'stock' => null]);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Order->value]);

    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $lens->id, 'quantity' => 2]);

    expect($lens->fresh()->stock)->toBeNull();
});

it('does not move stock on a quote', function () {
    $product = stockable(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Quote->value]);

    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 3]);

    expect($product->fresh()->stock)->toBe(10);
});

it('restores stock when the item is deleted', function () {
    $product = stockable(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Order->value]);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 3]);
    expect($product->fresh()->stock)->toBe(7);

    $item->delete();

    expect($product->fresh()->stock)->toBe(10);
});

it('adjusts stock by the delta when quantity changes', function () {
    $product = stockable(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Order->value]);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2]);
    expect($product->fresh()->stock)->toBe(8);

    $item->update(['quantity' => 5]); // +3 more
    expect($product->fresh()->stock)->toBe(5);

    $item->update(['quantity' => 1]); // back down by 4
    expect($product->fresh()->stock)->toBe(9);
});
