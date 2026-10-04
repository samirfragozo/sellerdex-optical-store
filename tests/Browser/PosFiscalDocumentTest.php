<?php

use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function posFiscalSeller(bool $externalManual): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    if ($externalManual) {
        $seller->company->update(['invoicing_mode' => InvoicingMode::ExternalManual, 'default_fiscal_document' => FiscalDocumentType::PosElectronic]);
    }
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'name' => 'Efectivo', 'is_active' => true, 'surcharge_percent' => 0]);
    $category = ProductCategory::factory()->create(['key' => 'frame', 'company_id' => $seller->company_id]);
    Product::factory()->create([
        'name' => 'Estuche rígido', 'product_category_id' => $category->id, 'company_id' => $seller->company_id,
        'is_active' => true, 'is_pos_selectable' => true, 'price' => 50_000,
    ]);

    return $seller;
}

it('asks for the missing buyer data when the cashier switches to factura', function () {
    $seller = posFiscalSeller(true);
    $customer = Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Marcela', 'last_name' => 'Rojas', 'email' => 'marcela@example.com']);
    $this->actingAs($seller);

    visit('/pos')
        ->fill('#customer_id', 'Marcela')
        ->wait(1)
        ->click('text=Marcela Rojas')
        ->click('.line-clamp-2')
        ->click('text=Cobrar')
        ->assertSee(__('app.fiscal_document_type.pos_electronic'))
        ->click('[data-testid="fiscal-document-electronic_invoice"]')
        ->select('[data-testid="buyer-person-type"]', 'natural')
        ->fill('[data-testid="buyer-dane-code"]', '11001')
        ->check('[data-testid="buyer-responsibility-R-99-PN"]')
        ->click('button:has-text("Efectivo")')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    expect(Sale::sole()->fiscal_document_type)->toBe(FiscalDocumentType::ElectronicInvoice)
        ->and($customer->fresh()->dane_municipality_code)->toBe('11001');
});

it('goes back to the default document when the customer changes', function () {
    $seller = posFiscalSeller(true);
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Marcela', 'last_name' => 'Rojas']);
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Pedro', 'last_name' => 'Soto']);
    $this->actingAs($seller);

    visit('/pos')
        ->fill('#customer_id', 'Marcela')
        ->wait(1)
        ->click('text=Marcela Rojas')
        ->click('.line-clamp-2')
        ->click('text=Cobrar')
        ->click('[data-testid="fiscal-document-electronic_invoice"]')
        ->keys('[role="dialog"]', 'Escape')
        ->assertMissing('[role="dialog"]')
        ->click('button[aria-label="Quitar cliente"]')
        ->fill('#customer_id', 'Pedro')
        ->wait(1)
        ->click('text=Pedro Soto')
        ->click('text=Cobrar')
        ->assertVisible('[data-testid="fiscal-document-pos_electronic"][aria-pressed="true"]')
        ->assertNoJavaScriptErrors();
});

it('shows no document choice in receipt-only mode', function () {
    $seller = posFiscalSeller(false);
    $this->actingAs($seller);

    visit('/pos')
        ->click('.line-clamp-2')
        ->click('text=Cobrar')
        ->assertSee('Monto a pagar')
        ->assertMissing('[data-testid="fiscal-document-electronic_invoice"]')
        ->assertNoJavaScriptErrors();
});
