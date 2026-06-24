<?php

use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stockableProduct(int $stock = 10): Product
{
    return Product::factory()->create([
        'product_category_id' => ProductCategory::factory()->create()->id,
        'is_stockable' => true,
        'stock' => $stock,
    ]);
}

it('restores stock when a sale is voided and re-deducts when reactivated', function () {
    $product = stockableProduct(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Order->value]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 4]);
    expect($product->fresh()->stock)->toBe(6);

    $sale->update(['status' => SaleStatus::Voided->value]);
    expect($product->fresh()->stock)->toBe(10);

    $sale->update(['status' => SaleStatus::Draft->value]);
    expect($product->fresh()->stock)->toBe(6);
});

it('does not restore stock for a voided quote (never deducted)', function () {
    $product = stockableProduct(10);
    $sale = Sale::factory()->create(['document_type' => SaleDocumentType::Quote->value]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 4]);
    expect($product->fresh()->stock)->toBe(10);

    $sale->update(['status' => SaleStatus::Voided->value]);
    expect($product->fresh()->stock)->toBe(10);
});
