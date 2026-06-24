<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $this->seed(PaymentMethodSeeder::class);
});

it('renders a combo invoice with free exam, included lines and the surcharged total', function () {
    $lens = Product::where('sku', 'ML-003')->first(); // Foto Blue, 295000
    $addi = PaymentMethod::where('name', 'Addi')->first(); // 7%

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'items' => [
            ['product_id' => $lens->id, 'description' => $lens->name, 'quantity' => 1, 'unit_price' => $lens->price, 'unit_cost' => $lens->cost],
        ],
        'combo' => ['with_exam' => true, 'forro' => 'small', 'include_liquid' => false],
        'surcharge_percent' => $addi->surcharge_percent,
    ], User::factory()->seller()->create());

    // lens 295000 + 20000 exam = 315000 subtotal; total with 7% = 337050
    expect($sale->total)->toBe(337050);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('documents.invoice', $sale))
        ->assertSuccessful()
        ->assertSee('GRATIS')       // examen visual line
        ->assertSee('Incluido')     // forro / paño / bolsa $0 lines
        ->assertSee('337.050')      // surcharged total
        ->assertDontSee('Subtotal'); // hidden when surcharged
});
