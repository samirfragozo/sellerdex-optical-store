<?php

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductCategory;
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

it('rejects sale creation when the seller has no open cash register session', function () {
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'products' => [['description' => 'Estuche', 'quantity' => 1, 'unit_price' => 10_000]],
    ])->assertForbidden()
        ->assertJson(['message' => __('app.pos.cash_session.required_notice')]);

    expect(Sale::count())->toBe(0);
});

it('stores a sale from the pos with an existing customer and split payments', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    $customer = Customer::factory()->create();
    $method = PaymentMethod::factory()->create();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'layaway',
        'products' => [['description' => 'Promo', 'quantity' => 1, 'unit_price' => 375_000]],
        'payments' => [['payment_method_id' => $method->id, 'amount' => 50_000]],
    ])->assertOk()->assertJsonStructure(['id', 'number', 'invoice_url', 'invoice_pdf_url']);

    $sale = Sale::first();
    expect($sale)->not->toBeNull()
        ->and($sale->customer_id)->toBe($customer->id)
        ->and($sale->total)->toBe(375_000)
        ->and($sale->balance)->toBe(325_000);
});

it('creates a new customer inline when none is selected', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);

    $this->actingAs($seller)->postJson('/pos', [
        'customer' => ['name' => 'Lina', 'last_name' => 'Quintero', 'phone' => '3044211489', 'document_type' => 'cc', 'id_number' => '123456'],
        'document_type' => 'order',
        'products' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100_000]],
    ])->assertOk();

    expect(Customer::where('name', 'Lina')->where('last_name', 'Quintero')->exists())->toBeTrue()
        ->and(Sale::count())->toBe(1);
});

it('returns a specific spanish error when neither armados nor products are provided', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->postJson('/pos', [
            'document_type' => 'order',
        ])
        ->assertJsonValidationErrors(['armados' => 'Agrega al menos un lente o un producto a la venta.']);
});

it('returns a specific spanish error for a product with an invalid quantity', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->postJson('/pos', [
            'customer_id' => Customer::factory()->create()->id,
            'document_type' => 'order',
            'products' => [['description' => 'Lente', 'quantity' => 0, 'unit_price' => 100_000]],
        ])
        ->assertJsonValidationErrors(['products.0.quantity' => 'La cantidad debe ser al menos 1.']);
});

it('accepts an existing customer even if a null customer payload is sent', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    $customer = Customer::factory()->create();

    // The POS sends `customer: null` (not an object) when an existing one is picked.
    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'customer' => null,
        'document_type' => 'order',
        'products' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100_000]],
    ])->assertOk();

    expect(Sale::where('customer_id', $customer->id)->exists())->toBeTrue();
});

it('creates an inline customer with a document type', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);

    $this->actingAs($seller)->postJson('/pos', [
        'customer' => ['name' => 'Lina', 'last_name' => 'Quintero', 'phone' => '3044211489', 'document_type' => 'cc', 'id_number' => '123'],
        'document_type' => 'order',
        'products' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100_000]],
    ])->assertOk();

    expect(Customer::where('name', 'Lina')->value('document_type'))
        ->toBe(DocumentType::CC);
});

it('rejects an invalid customer document type without a server error', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)->postJson('/pos', [
        'customer' => ['name' => 'Lina', 'document_type' => 'CC'],
        'document_type' => 'order',
        'products' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100_000]],
    ])->assertJsonValidationErrors('customer.document_type');

    expect(Customer::count())->toBe(0);
});

function lensProduct(): Product
{
    return Product::factory()->create([
        'product_category_id' => ProductCategory::factory()->create(['name' => 'Lente', 'key' => 'lens'])->id,
    ]);
}

it('enforces prescription validation even after the lens category is renamed', function () {
    $seller = User::factory()->seller()->create();
    $lensCategory = ProductCategory::factory()->create(['name' => 'Lente', 'key' => 'lens']);
    $lens = Product::factory()->create(['product_category_id' => $lensCategory->id]);

    // Rename the category display name — only the stable key should matter.
    $lensCategory->update(['name' => 'Lentes oftálmicos']);

    $this->actingAs($seller)->postJson('/pos', [
        'customer' => null,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => 'Lente', 'unit_price' => 100_000],
            'own_frame' => true,
        ]],
    ])->assertJsonValidationErrors([
        'customer' => 'La venta de lentes formulados requiere un cliente.',
        'prescription' => 'La venta de lentes formulados requiere una prescripción.',
    ]);

    expect(Sale::count())->toBe(0);
});

it('rejects payments that sum to more than the sale total', function () {
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();
    $method = PaymentMethod::factory()->create();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'products' => [['description' => 'Montura', 'quantity' => 1, 'unit_price' => 100_000]],
        'payments' => [['payment_method_id' => $method->id, 'amount' => 150_000]],
    ])->assertJsonValidationErrors(['payments' => 'La suma de los abonos no puede superar el total de la venta.']);

    expect(Sale::count())->toBe(0);
});

it('hides non-pos-selectable products from the pos picker', function () {
    $seller = User::factory()->seller()->create();
    $this->actingAs($seller);
    $sellable = Product::factory()->create(['is_pos_selectable' => true]);
    $hidden = Product::factory()->create(['is_pos_selectable' => false]);

    $response = $this->get('/pos');
    $ids = collect($response->viewData('page')['props']['products'])->pluck('id');

    expect($ids)->toContain($sellable->id)
        ->and($ids)->not->toContain($hidden->id);
});

it('allows a non-lens sale without any customer', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);

    $this->actingAs($seller)->postJson('/pos', [
        'customer' => null,
        'document_type' => 'order',
        'products' => [['description' => 'Estuche', 'quantity' => 1, 'unit_price' => 10_000]],
    ])->assertOk();

    expect(Sale::first()->customer_id)->toBeNull();
});

it('blocks selling a lens without a customer or prescription', function () {
    $seller = User::factory()->seller()->create();
    $lens = lensProduct();

    $this->actingAs($seller)->postJson('/pos', [
        'customer' => null,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => 'Lente', 'unit_price' => 100_000],
            'own_frame' => true,
        ]],
    ])->assertJsonValidationErrors([
        'customer' => 'La venta de lentes formulados requiere un cliente.',
        'prescription' => 'La venta de lentes formulados requiere una prescripción.',
    ]);

    expect(Sale::count())->toBe(0);
});

it('creates and links an inline prescription when selling a lens', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    $customer = Customer::factory()->create();
    $lens = lensProduct();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => 'Lente', 'unit_price' => 100_000],
            'own_frame' => true,
        ]],
        'prescription' => ['exam_date' => '2026-06-20', 'lens_type' => 'single_vision', 'od_sphere' => '-1.25', 'os_sphere' => '-1.00'],
    ])->assertOk();

    $sale = Sale::first();
    $prescription = Prescription::first();

    expect($prescription->customer_id)->toBe($customer->id)
        ->and($prescription->created_by)->toBe($seller->id)
        ->and($prescription->sale_id)->toBe($sale->id)
        ->and($sale->prescription_id)->toBe($prescription->id);
});

it('links an existing prescription that belongs to the customer when selling a lens', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    $this->actingAs($seller);
    $customer = Customer::factory()->create();
    $prescription = Prescription::factory()->create(['customer_id' => $customer->id]);
    $lens = lensProduct();

    $this->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => 'Lente', 'unit_price' => 100_000],
            'own_frame' => true,
        ]],
        'prescription_id' => $prescription->id,
    ])->assertOk();

    expect(Sale::first()->prescription_id)->toBe($prescription->id)
        ->and(Prescription::count())->toBe(1);
});

it('rejects a prescription that belongs to another customer', function () {
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();
    $otherPrescription = Prescription::factory()->create();
    $lens = lensProduct();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => 'Lente', 'unit_price' => 100_000],
            'own_frame' => true,
        ]],
        'prescription_id' => $otherPrescription->id,
    ])->assertJsonValidationErrors('prescription_id');

    expect(Sale::count())->toBe(0);
});

it('rejects prescription diopters out of range or off-step', function () {
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();
    $lens = lensProduct();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => 'Lente', 'unit_price' => 100_000],
            'own_frame' => true,
        ]],
        'prescription' => ['exam_date' => '2026-06-20', 'od_sphere' => '99', 'os_sphere' => '-2.30'],
    ])->assertJsonValidationErrors(['prescription.od_sphere', 'prescription.os_sphere']);
});

it('requires the axis when a cylinder is provided', function () {
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();
    $lens = lensProduct();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => 'Lente', 'unit_price' => 100_000],
            'own_frame' => true,
        ]],
        'prescription' => ['exam_date' => '2026-06-20', 'od_cylinder' => '-1.00'],
    ])->assertJsonValidationErrors(['prescription.od_axis' => 'Indica el eje cuando hay cilindro.']);
});

it('rejects an exam date older than two years', function () {
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();
    $lens = lensProduct();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => 'Lente', 'unit_price' => 100_000],
            'own_frame' => true,
        ]],
        'prescription' => ['exam_date' => now()->subYears(3)->toDateString()],
    ])->assertJsonValidationErrors(['prescription.exam_date' => 'La fecha del examen no puede tener más de 2 años.']);
});

it('returns the created sale as json for printing', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    $customer = Customer::factory()->create();

    $this->actingAs($seller)
        ->postJson('/pos', [
            'customer_id' => $customer->id,
            'document_type' => 'order',
            'products' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100_000]],
        ])
        ->assertOk();

    $sale = Sale::first();
    $this->actingAs($seller)
        ->postJson('/pos', [
            'customer_id' => $customer->id,
            'document_type' => 'order',
            'products' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100_000]],
        ])
        ->assertJson(fn ($json) => $json->has('number')->etc());

    expect(Sale::count())->toBe(2)
        ->and($sale->number)->not->toBeNull();
});

it('passes combo options and applies a paper bag', function () {
    $company = Company::factory()->create();
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    ProductCategory::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);
    Product::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);

    $seller = User::factory()->forCompany($company)->seller()->create();
    openCashRegisterSession($seller);
    $this->actingAs($seller);
    // ML-022 = 1,000,000 (≥ bag threshold of 215,000)
    $lens = Product::where('sku', 'ML-022')->first();

    $this->postJson('/pos', [
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'unit_price' => $lens->price],
            'own_frame' => true,
            'combo' => ['forro' => 'small', 'include_liquid' => false, 'with_exam' => true],
        ]],
        'prescription' => ['exam_date' => '2026-06-20'],
    ])->assertOk();

    $sale = Sale::latest('id')->first();
    $skus = $sale->items->map(fn ($i) => Product::withoutGlobalScopes()->find($i->product_id)?->sku)->filter();
    expect($skus)->toContain('ACC-FORRO-SMALL', 'ACC-PANO', 'ACC-BOLSA-PAPEL', 'SRV-EXAMEN');
});

it('rejects a lens armado without a customer', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $lens = Product::where('sku', 'ML-001')->first();

    $this->actingAs(User::factory()->seller()->create())
        ->postJson('/pos', [
            'document_type' => 'order',
            'armados' => [[
                'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'unit_price' => $lens->price],
                'own_frame' => true,
            ]],
        ])
        ->assertJsonValidationErrors('customer');
});

it('accepts two armados where only one carries a frame', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $lensA = Product::where('sku', 'ML-001')->first();
    $lensB = Product::where('sku', 'ML-052')->first();
    $frame = Product::where('sku', 'MNT-COMPLETAS-ACETATO')->first();
    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);

    $this->actingAs($seller)
        ->postJson('/pos', [
            'document_type' => 'order',
            'customer_id' => $customer->id,
            'prescription' => ['exam_date' => now()->toDateString(), 'od_sphere' => '-1.00'],
            'armados' => [
                [
                    'lens' => ['product_id' => $lensA->id, 'description' => $lensA->name, 'unit_price' => $lensA->price],
                    'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => $frame->price],
                    'own_frame' => false,
                ],
                [
                    'lens' => ['product_id' => $lensB->id, 'description' => $lensB->name, 'unit_price' => $lensB->price],
                    'frame' => null,
                    'own_frame' => true,
                ],
            ],
        ])
        ->assertOk();
});

it('rejects a lens armado without a prescription', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $lens = Product::where('sku', 'ML-001')->first();

    $this->actingAs(User::factory()->seller()->create())
        ->postJson('/pos', [
            'document_type' => 'order',
            'customer_id' => Customer::factory()->create()->id,
            'armados' => [[
                'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'unit_price' => $lens->price],
                'own_frame' => true,
            ]],
        ])
        ->assertJsonValidationErrors('prescription');
});

it('creates a sale from an armado with a new prescription', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $lens = Product::where('sku', 'ML-052')->first();
    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);

    $this->actingAs($seller)
        ->postJson('/pos', [
            'document_type' => 'order',
            'customer_id' => $customer->id,
            'prescription' => ['exam_date' => now()->toDateString(), 'od_add' => '2.00'],
            'armados' => [[
                'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'unit_price' => $lens->price],
                'own_frame' => true,
                'combo' => ['with_exam' => false, 'forro' => 'small', 'include_liquid' => false],
            ]],
        ])
        ->assertOk();

    $sale = Sale::latest('id')->first();
    expect($sale->customer_id)->toBe($customer->id)
        ->and($sale->prescription_id)->not->toBeNull()
        ->and($sale->items->firstWhere('product_id', $lens->id)->group_key)->toBe('g1');
});

// flujo real multi-empresa: un seller solo ve productos de su propia empresa
it('a seller only sees products from their own company in pos props', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $productA = Product::factory()->create(['company_id' => $companyA->id, 'is_pos_selectable' => true]);
    $productB = Product::factory()->create(['company_id' => $companyB->id, 'is_pos_selectable' => true]);

    $seller = User::factory()->forCompany($companyA)->seller()->create();

    $ids = collect(
        $this->actingAs($seller)->get('/pos')->viewData('page')['props']['products']
    )->pluck('id');

    expect($ids)->toContain($productA->id)
        ->and($ids)->not->toContain($productB->id);
});

it('exposes lens specs in the POS props', function () {
    $company = Company::factory()->create();
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    ProductCategory::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);
    Product::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);

    $this->actingAs(User::factory()->forCompany($company)->seller()->create())
        ->get('/pos')
        ->assertInertia(fn ($page) => $page
            ->component('Pos')
            ->where('products.0.category_key', fn ($key) => is_string($key) || $key === null)
        );
});

it('exposes the acting seller open cash session on the pos page', function () {
    $seller = User::factory()->seller()->create();
    $session = openCashRegisterSession($seller, 75_000);

    $this->actingAs($seller)
        ->get('/pos')
        ->assertInertia(fn ($page) => $page
            ->component('Pos')
            ->where('cashRegisterSession.id', $session->id)
            ->where('cashRegisterSession.opening_cash', 75_000)
        );
});

it('exposes a null cash session when the seller has none open', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get('/pos')
        ->assertInertia(fn ($page) => $page
            ->component('Pos')
            ->where('cashRegisterSession', null)
        );
});

it('rejects an option id that does not exist', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    $customer = Customer::factory()->create();
    $lens = lensProduct();

    $response = $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'prescription' => ['exam_date' => now()->toDateString()],
        'armados' => [[
            'lens' => [
                'product_id' => $lens->id,
                'description' => $lens->name,
                'unit_price' => 100000,
                'option_ids' => [999999],
            ],
            'own_frame' => true,
        ]],
    ]);

    $response->assertJsonValidationErrors(['armados.0.lens.option_ids.0']);
});

it('includes lens product option groups on the pos payload', function () {
    $company = Company::factory()->create();
    $seller = User::factory()->forCompany($company)->seller()->create();
    $this->actingAs($seller);

    $category = ProductCategory::factory()->create(['company_id' => $company->id, 'key' => 'lens']);
    $lens = Product::factory()->create(['company_id' => $company->id, 'product_category_id' => $category->id, 'is_active' => true, 'is_pos_selectable' => true]);
    $group = OptionGroup::factory()->create(['company_id' => $company->id, 'name' => 'Filtro', 'is_required' => true]);
    $option = Option::factory()->for($group, 'group')->create(['name' => 'Blue Cut', 'price' => 70000, 'cost' => 20000]);
    $lens->optionGroups()->attach($group->id, ['sort_order' => 1]);

    $response = $this->get('/pos');
    $products = collect($response->viewData('page')['props']['products']);
    $lensData = $products->firstWhere('id', $lens->id);

    expect($lensData)->not->toBeNull()
        ->and($lensData['option_groups'])->toHaveCount(1)
        ->and($lensData['option_groups'][0]['options'])->toHaveCount(1)
        ->and($lensData['option_groups'][0]['options'][0]['name'])->toBe('Blue Cut');
});

it('includes frame variant products on the pos payload', function () {
    $company = Company::factory()->create();
    $seller = User::factory()->forCompany($company)->seller()->create();
    $this->actingAs($seller);

    $category = ProductCategory::factory()->create(['company_id' => $company->id, 'key' => 'frame']);
    $base = Product::factory()->create(['company_id' => $company->id, 'product_category_id' => $category->id, 'is_active' => true, 'is_pos_selectable' => true]);
    $group = OptionGroup::factory()->create(['company_id' => $company->id, 'name' => 'Estructura', 'is_required' => true]);
    $option = Option::factory()->for($group, 'group')->create(['name' => 'Completas']);
    $base->optionGroups()->attach($group->id, ['sort_order' => 1]);

    $variant = Product::factory()->create([
        'company_id' => $company->id,
        'product_category_id' => $category->id,
        'base_product_id' => $base->id,
        'price' => 90000,
        'cost' => 40000,
        'stock' => 5,
        'is_pos_selectable' => false,
    ]);
    $variant->variantOptions()->attach($option->id);

    $response = $this->get('/pos');
    $products = collect($response->viewData('page')['props']['products']);
    $baseData = $products->firstWhere('id', $base->id);

    expect($baseData)->not->toBeNull()
        ->and($baseData['variants'])->toHaveCount(1)
        ->and($baseData['variants'][0]['id'])->toBe($variant->id)
        ->and($baseData['variants'][0]['price'])->toBe(90000)
        ->and($baseData['variants'][0]['option_ids'])->toBe([$option->id])
        ->and($products->firstWhere('id', $variant->id))->toBeNull();
});

it('applies discount percent, tip percent and per-line tax when registering a pos sale', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['company_id' => $seller->company_id, 'price' => 100_000, 'tax_rate' => 19, 'is_pos_selectable' => true]);

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'discount_percent' => 10,
        'tip_percent' => 5,
        'products' => [[
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 100_000,
        ]],
    ])->assertOk();

    $sale = Sale::first();
    expect($sale->discount_percent)->toBe('10.00')
        ->and($sale->tip_percent)->toBe('5.00')
        ->and($sale->items()->where('product_id', $product->id)->first()->tax_amount)->toBe(19_000);
});

it('rejects a discount_percent over 100', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    $customer = Customer::factory()->create();

    $this->actingAs($seller)->postJson('/pos', [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'discount_percent' => 150,
        'products' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 10_000]],
    ])->assertJsonValidationErrors('discount_percent');
});
