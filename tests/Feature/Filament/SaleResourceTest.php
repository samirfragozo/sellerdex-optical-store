<?php

use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Filament\Actions\ForceDeleteAction;
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

it('hides force delete on a trashed sale that has payments or returns', function () {
    $this->actingAs(User::factory()->admin()->create());
    $clean = Sale::factory()->create();
    $paid = Sale::factory()->create();
    Payment::factory()->create(['sale_id' => $paid->id]);
    $returned = Sale::factory()->create();
    SaleReturn::factory()->create(['sale_id' => $returned->id]);
    collect([$clean, $paid, $returned])->each->delete();

    Livewire::test(EditSale::class, ['record' => $clean->getRouteKey()])->assertActionVisible(ForceDeleteAction::class);
    Livewire::test(EditSale::class, ['record' => $paid->getRouteKey()])->assertActionHidden(ForceDeleteAction::class);
    Livewire::test(EditSale::class, ['record' => $returned->getRouteKey()])->assertActionHidden(ForceDeleteAction::class);
});
