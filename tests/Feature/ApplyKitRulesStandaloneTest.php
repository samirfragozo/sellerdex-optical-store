<?php

use App\Actions\RegisterSale;
use App\Enums\KitPriceMode;
use App\Enums\KitTrigger;
use App\Models\Customer;
use App\Models\KitSlot;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use App\Support\ReferenceKit;

require_once __DIR__.'/../Support/GoldenCatalog.php';

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->catalog = goldenCatalog($this->seller);
});

function kitSellProducts(array $lines): Sale
{
    return app(RegisterSale::class)->handle(['document_type' => 'order', 'products' => $lines], test()->seller);
}

function kitLine(Product $p, int $price, int $qty = 1): array
{
    return ['product_id' => $p->id, 'description' => $p->name, 'quantity' => $qty, 'unit_price' => $price];
}

it('adds nothing for a company without combo slots', function () {
    $sale = kitSellProducts([kitLine($this->catalog['frame'], 150_000)]);

    expect($sale->items)->toHaveCount(1);
});

it('uses the default bag one peso below the threshold and the upgrade exactly at it', function () {
    ReferenceKit::installFor($this->seller->company);
    $item = Product::factory()->create(['product_category_id' => ProductCategory::keyed('cloth')->id, 'is_active' => true]);

    $below = kitSellProducts([kitLine($item, 214_999)]);
    $at = kitSellProducts([kitLine($item, 215_000)]);

    expect($below->items->pluck('product.sku'))->toContain('ACC-BOLSA-PLASTICO')->not->toContain('ACC-BOLSA-PAPEL')
        ->and($at->items->pluck('product.sku'))->toContain('ACC-BOLSA-PAPEL')->not->toContain('ACC-BOLSA-PLASTICO');
});

it('adds a specific-product slot once per sale (replaces product additions)', function () {
    $contactLens = Product::factory()->create(['price' => 90_000, 'is_active' => true]);
    $solution = Product::where('sku', 'ACC-LIQUIDO')->sole();
    KitSlot::factory()->create([
        'trigger' => KitTrigger::Product, 'trigger_product_id' => $contactLens->id,
        'slot_category_id' => $solution->product_category_id, 'default_product_id' => $solution->id,
        'price_mode' => KitPriceMode::DiscountPercent, 'price_value' => 50, 'quantity' => 1,
    ]);

    $sale = kitSellProducts([kitLine($contactLens, 90_000, 2)]);
    $solutionLines = $sale->items->where('product_id', $solution->id);

    expect($solutionLines)->toHaveCount(1)->and($solutionLines->first()->unit_price)->toBe(4_000);
});

it('does not add a standalone-category slot for a frame inside an armado', function () {
    ReferenceKit::installFor($this->seller->company);
    $frame = $this->catalog['frame'];

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => [
                'description' => 'Lente', 'treatment_ids' => [],
                'lens_type_id' => $this->catalog['lens']->lens_type_id,
                'lens_technology_id' => $this->catalog['lens']->lens_technology_id,
                'lens_material_id' => $this->catalog['lens']->lens_material_id,
            ],
            'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => 150_000],
        ]],
    ], $this->seller);

    expect($sale->items->pluck('product.sku'))->not->toContain('ACC-FUNDA');
});
