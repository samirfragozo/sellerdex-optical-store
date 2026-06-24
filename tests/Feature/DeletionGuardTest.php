<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks deleting a product category that has products', function () {
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['product_category_id' => $category->id]);

    expect($category->hasChildren())->toBeTrue()
        ->and($category->delete())->toBeFalse()
        ->and(ProductCategory::whereKey($category->id)->exists())->toBeTrue();
});

it('allows deleting a product category with no products', function () {
    $category = ProductCategory::factory()->create();

    expect($category->delete())->toBeTrue()
        ->and(ProductCategory::whereKey($category->id)->exists())->toBeFalse();
});

it('blocks deleting an expense category that has expenses', function () {
    $category = ExpenseCategory::factory()->create();
    Expense::factory()->create(['expense_category_id' => $category->id]);

    expect($category->hasChildren())->toBeTrue()
        ->and($category->delete())->toBeFalse()
        ->and(ExpenseCategory::whereKey($category->id)->exists())->toBeTrue();
});

it('blocks deleting a payment method that has payments', function () {
    $method = PaymentMethod::factory()->create(['is_default' => false]);
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 100000]);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $method->id, 'amount' => 1000]);

    expect($method->hasChildren())->toBeTrue()
        ->and($method->delete())->toBeFalse()
        ->and(PaymentMethod::whereKey($method->id)->exists())->toBeTrue();
});
