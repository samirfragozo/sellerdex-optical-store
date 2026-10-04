<?php

use App\Actions\ConvertQuoteToOrder;
use App\Actions\RegisterSale;
use App\Enums\ArmadoFramePriceMode;
use App\Enums\SaleDocumentType;
use App\Models\Customer;
use App\Models\KitSlot;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->admin->company->update(['quote_validity_days' => 15]);
});

/** Registers an expired armado quote (lens + catalog frame + slot line) and returns it with its fixtures. */
function quoteValidityArmadoQuote(object $test): array
{
    $test->seed(ProductCategorySeeder::class);
    $test->seed(ProductCatalogSeeder::class);
    $test->admin->company->update(['armado_frame_price_mode' => ArmadoFramePriceMode::Normal]);

    $frame = Product::where('sku', 'MNT-COMPLETA-ACETATO')->first();
    $estuche = Product::where('sku', 'ACC-ESTUCHE-SMALL')->first();
    $slot = KitSlot::factory()->create(['slot_category_id' => $estuche->product_category_id, 'default_product_id' => $estuche->id]);
    $combination = LensCombination::factory()->priced(180_000, 60_000)->create(['installation_price' => 3_000]);

    $quote = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'quote',
        'armados' => [[
            'lens' => [
                'description' => 'Lente formulado',
                'quantity' => 1,
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
                'treatment_ids' => [],
            ],
            'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => $frame->price],
            'slots' => [['kit_slot_id' => $slot->id, 'product_id' => $estuche->id]],
        ]],
    ], $test->admin);

    $quote->update(['sold_at' => today()->subDays(30), 'quote_valid_until' => today()->subDays(15)]);

    return [$quote->fresh(), $frame, $estuche, $combination];
}

it('gives a quote its validity date from the company setting', function () {
    $quote = Sale::factory()->create(['document_type' => SaleDocumentType::Quote, 'sold_at' => '2026-10-01']);

    expect($quote->quote_valid_until->toDateString())->toBe('2026-10-16')
        ->and(Sale::factory()->create(['document_type' => SaleDocumentType::Order])->quote_valid_until)->toBeNull();
});

it('converts a quote still valid without touching its prices', function () {
    $product = Product::factory()->create(['price' => 50_000]);
    $quote = Sale::factory()->create(['document_type' => SaleDocumentType::Quote, 'sold_at' => today()]);
    SaleItem::factory()->create(['sale_id' => $quote->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50_000]);
    $product->update(['price' => 70_000]);

    expect(app(ConvertQuoteToOrder::class)->handle($quote))->toBeFalse();
    expect($quote->fresh())->document_type->toBe(SaleDocumentType::Order)->total->toBe(50_000);
});

it('re-prices an expired quote with current catalog prices and keeps free combo lines free', function () {
    $product = Product::factory()->create(['price' => 50_000]);
    $gift = Product::factory()->create(['price' => 8_000]);
    $quote = Sale::factory()->create(['document_type' => SaleDocumentType::Quote, 'sold_at' => today()->subDays(30)]);
    SaleItem::factory()->create(['sale_id' => $quote->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50_000]);
    SaleItem::factory()->create(['sale_id' => $quote->id, 'product_id' => $gift->id, 'quantity' => 1, 'unit_price' => 0]);
    $product->update(['price' => 70_000]);

    expect(app(ConvertQuoteToOrder::class)->handle($quote))->toBeTrue();
    expect($quote->fresh())->document_type->toBe(SaleDocumentType::Order)->total->toBe(70_000);
});

it('prints the validity date on a quote', function () {
    $quote = Sale::factory()->create(['document_type' => SaleDocumentType::Quote, 'sold_at' => '2026-10-01']);

    $this->get(route('documents.invoice', $quote))->assertSee(__('app.documents.quote_valid_until', ['date' => '16/10/2026']));
});

it('re-prices the lens and the armado frame of an expired quote and keeps slot lines', function () {
    [$quote, $frame, $estuche, $combination] = quoteValidityArmadoQuote($this);

    $lensBefore = $quote->items->first(fn ($item) => $item->isLens())->unit_price;
    $slotBefore = $quote->items->firstWhere('product_id', $estuche->id)->unit_price;

    LensCombinationPrice::where('lens_combination_id', $combination->id)->update(['price' => 190_000]);
    $frame->update(['price' => $frame->price + 5_000]);

    expect(app(ConvertQuoteToOrder::class)->handle($quote))->toBeTrue();

    $items = $quote->fresh()->items;
    expect($items->first(fn ($item) => $item->isLens())->unit_price)->toBe($lensBefore + 10_000)
        ->and($items->firstWhere('product_id', $frame->id)->unit_price)->toBe($this->admin->company->armadoFrameUnitPrice($frame->fresh()->price))
        ->and($items->firstWhere('product_id', $estuche->id)->unit_price)->toBe($slotBefore);
});

it('keeps an expired quote as a quote when its lens can no longer be priced', function () {
    [$quote, , , $combination] = quoteValidityArmadoQuote($this);

    LensCombinationPrice::where('lens_combination_id', $combination->id)->update(['is_active' => false]);

    expect(fn () => app(ConvertQuoteToOrder::class)->handle($quote))->toThrow(ValidationException::class);
    expect($quote->fresh()->document_type)->toBe(SaleDocumentType::Quote);
});

it('does not print the validity line once the quote became an order', function () {
    $quote = Sale::factory()->create(['document_type' => SaleDocumentType::Quote, 'sold_at' => today()]);

    app(ConvertQuoteToOrder::class)->handle($quote);

    $this->get(route('documents.invoice', $quote))->assertDontSee(__('app.documents.quote_valid_until', ['date' => $quote->fresh()->quote_valid_until->format('d/m/Y')]));
});

it('keeps an expired quote as a quote when its lens combination was deleted', function () {
    [$quote] = quoteValidityArmadoQuote($this);

    $quote->items->first(fn ($item) => $item->isLens())->lensConfig->update(['lens_combination_id' => null]);

    expect(fn () => app(ConvertQuoteToOrder::class)->handle($quote))->toThrow(ValidationException::class);
    expect($quote->fresh()->document_type)->toBe(SaleDocumentType::Quote);
});
