<?php

use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('el admin ve el listado de ventas', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/sales')
        ->assertSuccessful();
});

it('el vendedor también puede ver el listado de ventas', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get('/admin/sales')
        ->assertSuccessful();
});

it('renders the sale create page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/sales/create')
        ->assertSuccessful();
});

it('renders the sale edit page with its items', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id]);

    $this->get("/admin/sales/{$sale->id}/edit")
        ->assertSuccessful();
});

it('resets the selected prescription when the sale customer changes', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();
    $prescription = Prescription::factory()->create(['customer_id' => $customerA->id]);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $customerA->id,
            'prescription_id' => $prescription->id,
        ])
        ->set('data.customer_id', $customerB->id)
        ->assertSchemaStateSet(['prescription_id' => null]);
});

it('labels the prescription select with the exam and expiry dates, scoped to the sale customer', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $customer = Customer::factory()->create();
    $prescription = Prescription::factory()->create([
        'customer_id' => $customer->id,
        'exam_date' => now()->subMonth()->toDateString(),
    ]);
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'prescription_id' => $prescription->id]);

    $this->get("/admin/sales/{$sale->id}/edit")
        ->assertSuccessful()
        ->assertSee(__('app.fields.prescription_option', [
            'exam_date' => $prescription->exam_date->toDateString(),
            'expires_at' => $prescription->expires_at->toDateString(),
        ]));
});

it('does not resolve a prescription belonging to a different customer as the selected option', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $customer = Customer::factory()->create();
    $othersPrescription = Prescription::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    // Bypasses StoreSaleRequest on purpose — this simulates a mismatched row
    // reaching the admin edit form, which the Select's own query must still guard.
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'prescription_id' => $othersPrescription->id]);

    $this->get("/admin/sales/{$sale->id}/edit")
        ->assertSuccessful()
        ->assertDontSee(__('app.fields.prescription_option', [
            'exam_date' => $othersPrescription->exam_date->toDateString(),
            'expires_at' => $othersPrescription->expires_at->toDateString(),
        ]));
});

it('rejects a crafted prescription that belongs to another customer on save', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $customer = Customer::factory()->create();
    $othersPrescription = Prescription::factory()->create(['customer_id' => Customer::factory()->create()->id]);

    Livewire::test(CreateSale::class)
        ->fillForm(['customer_id' => $customer->id])
        ->set('data.prescription_id', $othersPrescription->id)
        ->call('create')
        ->assertHasFormErrors(['prescription_id']);

    expect(Sale::where('prescription_id', $othersPrescription->id)->exists())->toBeFalse();
});

it('rejects a crafted customer from another company on save', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $foreignCustomer = Customer::withoutGlobalScopes()->create([
        ...Customer::factory()->make()->getAttributes(),
        'company_id' => User::factory()->admin()->create()->company_id,
    ]);

    Livewire::test(CreateSale::class)
        ->set('data.customer_id', $foreignCustomer->id)
        ->call('create')
        ->assertHasFormErrors(['customer_id']);
});

it('accepts the sale customer\'s own prescription on save', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $customer = Customer::factory()->create();
    $prescription = Prescription::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(CreateSale::class)
        ->fillForm(['customer_id' => $customer->id])
        ->set('data.prescription_id', $prescription->id)
        ->call('create')
        ->assertHasNoFormErrors(['prescription_id']);
});
