<?php

use App\Enums\SaleDocumentType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function docTypeStockable(int $stock = 10): Product
{
    return Product::factory()->create([
        'product_category_id' => ProductCategory::factory()->create()->id,
        'is_stockable' => true,
        'stock' => $stock,
    ]);
}

it('reserves stock for a layaway without deducting it on creation', function () {
    $product = docTypeStockable(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Layaway->value]);

    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 3]);

    expect($product->fresh()->stock)->toBe(10);
});

it('deducts layaway stock on delivery and restores it when undone', function () {
    $product = docTypeStockable(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Layaway->value]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 3]);

    $sale->update(['is_delivered' => true]);
    expect($product->fresh()->stock)->toBe(7);

    $sale->update(['is_delivered' => false]);
    expect($product->fresh()->stock)->toBe(10);
});

it('deducts stock when a quote is converted into an order', function () {
    $product = docTypeStockable(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Quote->value]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 4]);
    expect($product->fresh()->stock)->toBe(10);

    $sale->update(['document_type' => SaleDocumentType::Order->value]);

    expect($product->fresh()->stock)->toBe(6);
});
