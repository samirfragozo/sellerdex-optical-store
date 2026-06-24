<?php

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for the pos page', function () {
    $this->get('/pos')->assertRedirect();
});

it('renders the pos page for an authenticated seller', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get('/pos')
        ->assertSuccessful();
});

it('stores a sale from the pos with an existing customer and a payment', function () {
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();
    $method = PaymentMethod::factory()->create();

    $this->actingAs($seller)->post('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'layaway',
        'items' => [['description' => 'Promo', 'quantity' => 1, 'unit_price' => 375_000]],
        'payment' => ['payment_method_id' => $method->id, 'amount' => 50_000],
    ])->assertRedirect();

    $sale = Sale::first();
    expect($sale)->not->toBeNull()
        ->and($sale->customer_id)->toBe($customer->id)
        ->and($sale->total)->toBe(375_000)
        ->and($sale->balance)->toBe(325_000);
});

it('creates a new customer inline when none is selected', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)->post('/pos', [
        'customer' => ['name' => 'Lina', 'last_name' => 'Quintero', 'phone' => '3044211489'],
        'document_type' => 'order',
        'items' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100_000]],
    ])->assertRedirect();

    expect(Customer::where('name', 'Lina')->where('last_name', 'Quintero')->exists())->toBeTrue()
        ->and(Sale::count())->toBe(1);
});

it('passes combo options and applies a paper bag', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $seller = User::factory()->seller()->create();
    $lens = Product::where('sku', 'ML-003')->first(); // 295000

    $this->actingAs($seller)->post('/pos', [
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'items' => [['product_id' => $lens->id, 'description' => $lens->name, 'quantity' => 1, 'unit_price' => $lens->price]],
        'combo' => ['forro' => 'small', 'include_liquid' => false, 'with_exam' => true],
    ])->assertRedirect();

    $sale = Sale::latest('id')->first();
    $skus = $sale->items->map(fn ($i) => Product::find($i->product_id)?->sku)->filter();
    expect($skus)->toContain('ACC-FORRO-SMALL', 'ACC-PANO', 'ACC-BOLSA-PAPEL', 'SRV-EXAMEN');
});

it('flashes the created sale id and number for printing', function () {
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($seller)
        ->post('/pos', [
            'customer_id' => $customer->id,
            'document_type' => 'order',
            'items' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100_000]],
        ])
        ->assertSessionHas('createdSale');

    $sale = Sale::first();
    expect(session('createdSale'))->toMatchArray(['id' => $sale->id, 'number' => $sale->number]);
});
