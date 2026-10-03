<?php

use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Models\Customer;
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

it('shows the real margin on the sale edit page to admins only', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id]);

    $this->get("/admin/sales/{$sale->id}/edit")
        ->assertSuccessful()->assertSee('Margen real');

    $seller = User::factory()->seller()->create(['company_id' => $admin->company_id]);
    $this->actingAs($seller)->get("/admin/sales/{$sale->id}/edit")
        ->assertSuccessful()->assertDontSee('Margen real');
});
