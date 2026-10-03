<?php

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('spends the customer store credit and pays the rest in cash at checkout', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'name' => 'Efectivo', 'is_active' => true, 'surcharge_percent' => 0]);
    PaymentMethod::storeCreditFor($seller->company_id)
        ?? PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'name' => 'Saldo a favor', 'is_store_credit' => true, 'is_active' => true]);
    $customer = Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Marcela', 'last_name' => 'Rojas']);
    CustomerCredit::create(['company_id' => $seller->company_id, 'customer_id' => $customer->id, 'amount' => 30_000]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);
    Product::factory()->create([
        'name' => 'Estuche rígido',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
        'price' => 50_000,
    ]);
    $this->actingAs($seller);

    $page = visit('/pos')
        ->fill('#customer_id', 'Marcela')
        ->wait(1)
        ->click('text=Marcela Rojas')
        ->assertSee('Saldo a favor: $30.000')
        ->click('.line-clamp-2')
        ->click('text=Cobrar')
        ->click('button:has-text("Saldo a favor ($30.000)")')
        ->click('button:has-text("Efectivo")')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    $sale = Sale::sole();
    expect($customer->creditBalance())->toBe(0)
        ->and($sale->payments)->toHaveCount(2)
        ->and($sale->payments->pluck('amount')->sort()->values()->all())->toBe([20_000, 30_000]);
});

it('drops the store credit payment when the customer changes', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'name' => 'Efectivo', 'is_active' => true]);
    PaymentMethod::storeCreditFor($seller->company_id)
        ?? PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'name' => 'Saldo a favor', 'is_store_credit' => true, 'is_active' => true]);
    $marcela = Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Marcela', 'last_name' => 'Rojas']);
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Pedro', 'last_name' => 'Soto']);
    CustomerCredit::create(['company_id' => $seller->company_id, 'customer_id' => $marcela->id, 'amount' => 30_000]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);
    Product::factory()->create([
        'name' => 'Estuche rígido',
        'product_category_id' => $category->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
        'price' => 50_000,
    ]);
    $this->actingAs($seller);

    $page = visit('/pos')
        ->fill('#customer_id', 'Marcela')
        ->wait(1)
        ->click('text=Marcela Rojas')
        ->click('.line-clamp-2')
        ->click('text=Cobrar')
        ->click('button:has-text("Saldo a favor ($30.000)")')
        ->assertSee('Saldo a favor')
        ->keys('[role="dialog"]', 'Escape')
        ->wait(1)
        ->click('button[aria-label="Quitar cliente"]')
        ->fill('#customer_id', 'Pedro')
        ->wait(1)
        ->click('text=Pedro Soto')
        ->click('text=Cobrar')
        ->assertDontSee('Saldo a favor')
        ->assertNoJavaScriptErrors();
});
