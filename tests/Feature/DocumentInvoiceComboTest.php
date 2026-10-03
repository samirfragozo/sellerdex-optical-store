<?php

use App\Actions\RegisterSale;
use App\Enums\KitTrigger;
use App\Models\Company;
use App\Models\Customer;
use App\Models\KitSlot;
use App\Models\LensCombination;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\ReferenceKit;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a combo invoice with free exam, included lines and the surcharged total', function () {
    // Seed catalog and assign to a shared company so the scope finds all products.
    $company = Company::factory()->create();
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $this->seed(PaymentMethodSeeder::class);
    ProductCategory::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);
    Product::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);
    PaymentMethod::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);

    $seller = User::factory()->forCompany($company)->seller()->create();
    $admin = User::factory()->forCompany($company)->admin()->create();
    $this->actingAs($seller);
    ReferenceKit::installFor($company);
    $examSlot = KitSlot::where('trigger', KitTrigger::Armado)->get()->firstWhere(fn ($s) => $s->slotCategory->key === 'service');

    $combination = LensCombination::factory()->priced(295000, 6000)->create(['installation_price' => 0]);
    $addi = PaymentMethod::where('name', 'Addi')->first(); // 7%

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => [
                'description' => 'Lente Monofocal',
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
                'treatment_ids' => [],
            ],
            'own_frame' => true,
            'slots' => [['kit_slot_id' => $examSlot->id, 'product_id' => Product::where('sku', 'SRV-EXAMEN')->value('id'), 'selected' => true]],
        ]],
        'surcharge_percent' => $addi->surcharge_percent,
    ], $seller);

    // lens 295000 + 20000 exam = 315000 subtotal; total with 7% = 337050
    expect($sale->total)->toBe(337050);

    $this->actingAs($admin)
        ->get(route('documents.invoice', $sale))
        ->assertSuccessful()
        ->assertSee('GRATIS')       // examen visual line
        ->assertSee('Incluido')     // bolsa $0 line
        ->assertSee('337.050')      // surcharged total
        ->assertDontSee('Subtotal'); // hidden when surcharged
});
