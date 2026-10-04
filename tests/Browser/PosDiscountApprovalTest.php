<?php

use App\Models\CashRegisterSession;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('asks for the admin pin when the discount is above the seller cap', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    $admin = User::factory()->admin()->create(['company_id' => $seller->company_id, 'approval_pin' => '4321']);
    $seller->company->update(['seller_max_discount_percent' => 5]);
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'name' => 'Efectivo', 'is_active' => true, 'surcharge_percent' => 0]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);
    Product::factory()->create([
        'name' => 'Estuche rígido', 'product_category_id' => $category->id, 'company_id' => $seller->company_id,
        'is_active' => true, 'is_pos_selectable' => true, 'price' => 50_000,
    ]);
    $this->actingAs($seller);

    visit('/pos')
        ->click('.line-clamp-2')
        ->fill('[data-testid="discount-percent-input"]', '10')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee(__('app.approval.required'))
        ->fill('[data-testid="approval-pin-input"]', '4321')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    expect(Sale::sole()->discount_approved_by)->toBe($admin->id);
});
