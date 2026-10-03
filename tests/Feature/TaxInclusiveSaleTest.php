<?php

use App\Actions\GenerateProductVariants;
use App\Actions\RegisterSale;
use App\Enums\TaxTreatment;
use App\Enums\VatRegime;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    Tax::seedDefaultsFor($this->seller->company);
    $this->iva19 = Tax::where('name', 'IVA 19%')->sole();
});

function sellProduct(Product $product, array $extra = []): Sale
{
    return app(RegisterSale::class)->handle([
        'document_type' => 'order',
        'products' => [['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => $product->price]],
        ...$extra,
    ], test()->seller);
}

it('charges exactly the tax-inclusive price and derives the VAT inside it', function () {
    $frame = Product::factory()->create(['price' => 119_000, 'tax_id' => $this->iva19->id]);

    $sale = sellProduct($frame);
    $line = $sale->items->firstWhere('product_id', $frame->id);

    expect($sale->total)->toBe(119_000)
        ->and($sale->tax_amount)->toBe(19_000)
        ->and($line->tax_amount)->toBe(19_000)
        ->and($line->tax_name)->toBe('IVA 19%')
        ->and((float) $line->tax_rate)->toBe(19.0)
        ->and($line->tax_treatment)->toBe(TaxTreatment::Taxed);
});

it('applies a discount to the tax-inclusive price and prorates the VAT', function () {
    $frame = Product::factory()->create(['price' => 100_000, 'tax_id' => $this->iva19->id]);

    $sale = sellProduct($frame, ['discount_percent' => 10]);

    // line tax = 100000 - 100000/1.19 = 15966; prorated by 90000/100000 = 14369.4 → 14369
    expect($sale->total)->toBe(90_000)
        ->and($sale->tax_amount)->toBe(14_369);
});

it('never taxes lines for a company that is not VAT responsible', function () {
    $this->seller->company->update(['vat_regime' => VatRegime::NotResponsible]);
    $frame = Product::factory()->create(['price' => 119_000, 'tax_id' => $this->iva19->id]);

    $sale = sellProduct($frame);
    $line = $sale->items->firstWhere('product_id', $frame->id);

    expect($sale->total)->toBe(119_000)
        ->and($sale->tax_amount)->toBe(0)
        ->and($line->tax_amount)->toBe(0)
        ->and($line->tax_name)->toBeNull();
});

it('taxes an armado lens with its combination tax, falling back to the lens category tax', function () {
    $combination = LensCombination::factory()->priced(119_000, 1)->create(['installation_price' => 0, 'tax_id' => $this->iva19->id]);
    $customer = Customer::factory()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [['lens' => [
            'description' => 'Lente', 'lens_type_id' => $combination->lens_type_id,
            'lens_technology_id' => $combination->lens_technology_id, 'lens_material_id' => $combination->lens_material_id,
        ], 'own_frame' => true]],
    ], $this->seller);

    $lens = $sale->items->first(fn ($i) => $i->isLens());

    expect($lens->tax_name)->toBe('IVA 19%')
        ->and($lens->tax_amount)->toBe((int) round($lens->line_total - $lens->line_total / 1.19));
});

it('falls back to the lens category default tax when the combination has none', function () {
    ProductCategory::factory()->create(['key' => 'lens', 'default_tax_id' => $this->iva19->id]);
    $combination = LensCombination::factory()->priced(119_000, 1)->create(['installation_price' => 0, 'tax_id' => null]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [['lens' => [
            'description' => 'Lente', 'lens_type_id' => $combination->lens_type_id,
            'lens_technology_id' => $combination->lens_technology_id, 'lens_material_id' => $combination->lens_material_id,
        ], 'own_frame' => true]],
    ], $this->seller);

    expect($sale->items->first(fn ($i) => $i->isLens())->tax_name)->toBe('IVA 19%');
});

it('recomputes the line tax when the price changes after the line is created', function () {
    $frame = Product::factory()->create(['price' => 119_000, 'tax_id' => $this->iva19->id]);
    $sale = sellProduct($frame);
    $line = $sale->items->firstWhere('product_id', $frame->id);

    $line->update(['unit_price' => 238_000]);

    expect($line->fresh()->tax_amount)->toBe(38_000)
        ->and($sale->fresh()->total)->toBe(238_000);
});

it('accepts paying exactly the tax-inclusive total', function () {
    openCashRegisterSession($this->seller);
    $method = PaymentMethod::factory()->create(['is_active' => true]);
    Supplier::factory()->laboratory()->create();
    $frame = Product::factory()->create(['price' => 119_000, 'tax_id' => $this->iva19->id, 'is_pos_selectable' => true]);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'products' => [['product_id' => $frame->id, 'description' => $frame->name, 'quantity' => 1, 'unit_price' => 119_000]],
        'payments' => [['payment_method_id' => $method->id, 'amount' => 119_000]],
    ])->assertOk();
});

it('rejects payments above the tax-inclusive total', function () {
    openCashRegisterSession($this->seller);
    $method = PaymentMethod::factory()->create(['is_active' => true]);
    Supplier::factory()->laboratory()->create();
    $frame = Product::factory()->create(['price' => 119_000, 'tax_id' => $this->iva19->id, 'is_pos_selectable' => true]);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'products' => [['product_id' => $frame->id, 'description' => $frame->name, 'quantity' => 1, 'unit_price' => 119_000]],
        'payments' => [['payment_method_id' => $method->id, 'amount' => 119_001]],
    ])->assertUnprocessable()->assertJsonValidationErrors('payments');
});

it('taxes a generated variant with its base product tax', function () {
    $base = Product::factory()->create(['sku' => 'MNT-IVA', 'name' => 'Montura', 'tax_id' => $this->iva19->id]);
    $color = OptionGroup::factory()->create(['name' => 'Color']);
    Option::factory()->for($color, 'group')->create(['name' => 'Negro']);
    $base->optionGroups()->attach($color->id);

    app(GenerateProductVariants::class)->handle($base);
    $variant = $base->variants()->sole();
    $variant->update(['price' => 119_000]);

    $line = sellProduct($variant)->items->firstWhere('product_id', $variant->id);

    expect($variant->tax_id)->toBe($this->iva19->id)
        ->and($line->tax_name)->toBe('IVA 19%')
        ->and($line->tax_amount)->toBe(19_000);
});

it('taxes an older variant without its own tax with its base product tax', function () {
    $base = Product::factory()->create(['tax_id' => $this->iva19->id]);
    $variant = Product::factory()->create(['base_product_id' => $base->id, 'tax_id' => null, 'price' => 119_000]);

    $line = sellProduct($variant)->items->firstWhere('product_id', $variant->id);

    expect($line->tax_name)->toBe('IVA 19%')
        ->and($line->tax_amount)->toBe(19_000);
});

it('charges the payment-method surcharge on the tax-inclusive price without adding VAT to it', function () {
    $frame = Product::factory()->create(['price' => 119_000, 'tax_id' => $this->iva19->id]);

    $sale = sellProduct($frame, ['surcharge_percent' => 7]);

    expect($sale->total)->toBe(127_330)
        ->and($sale->tax_amount)->toBe(19_000);
});
