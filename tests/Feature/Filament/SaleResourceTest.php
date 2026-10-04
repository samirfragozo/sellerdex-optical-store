<?php

use App\Actions\RegisterFiscalDocument;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Filament\Actions\DeleteAction;
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
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $admin->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]); // no automatic receipt
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

it('refuses to force delete a sale that has payments or returns, but not a clean one', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $admin->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]); // no automatic receipt
    $clean = Sale::factory()->create();
    $paid = Sale::factory()->create();
    $payment = Payment::factory()->create(['sale_id' => $paid->id]);
    $returned = Sale::factory()->create();
    SaleReturn::factory()->create(['sale_id' => $returned->id]);
    collect([$clean, $paid, $returned])->each->delete();

    expect($paid->forceDelete())->toBeFalse()
        ->and($returned->forceDelete())->toBeFalse()
        ->and(Sale::withTrashed()->find($paid->id))->not->toBeNull()
        ->and(Payment::withTrashed()->find($payment->id))->not->toBeNull()
        ->and(Sale::withTrashed()->find($returned->id))->not->toBeNull()
        ->and($clean->forceDelete())->toBeTrue()
        ->and(Sale::withTrashed()->find($clean->id))->toBeNull();
});

it('keeps sales with payments when force deleting in bulk from the list', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $admin->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]); // no automatic receipt
    $clean = Sale::factory()->create();
    $paid = Sale::factory()->create();
    Payment::factory()->create(['sale_id' => $paid->id]);
    collect([$clean, $paid])->each->delete();

    Livewire::test(ListSales::class)
        ->filterTable('trashed', true)
        ->callTableBulkAction('forceDelete', [$clean->id, $paid->id]);

    expect(Sale::withTrashed()->find($paid->id))->not->toBeNull()
        ->and(Sale::withTrashed()->find($clean->id))->toBeNull();
});

it('stops a seller from raising a sale discount above the company cap', function () {
    $seller = User::factory()->seller()->create();
    $seller->company->update(['seller_max_discount_percent' => 5]);
    $this->actingAs($seller);
    $sale = Sale::factory()->create();

    Livewire::test(EditSale::class, ['record' => $sale->getRouteKey()])
        ->fillForm(['discount_percent' => 10])
        ->call('save')
        ->assertHasFormErrors(['discount_percent']);
});

it('freezes a sale with a registered POS document and refuses to delete it', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $admin->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]);
    $documented = Sale::factory()->create();
    $plain = Sale::factory()->create();
    app(RegisterFiscalDocument::class)->forSale($documented, FiscalDocumentType::PosElectronic, 'POS-LOCK-1', today(), $admin);

    expect($documented->isLockedForEdits())->toBeTrue()
        ->and($plain->isLockedForEdits())->toBeFalse()
        ->and($documented->delete())->toBeFalse()
        ->and($documented->fresh()->trashed())->toBeFalse()
        ->and($plain->delete())->toBeTrue();

    Livewire::test(EditSale::class, ['record' => $documented->getRouteKey()])
        ->assertFormFieldDisabled('discount_percent')
        ->assertActionHidden(DeleteAction::class);

    $other = Sale::factory()->create();
    Livewire::test(EditSale::class, ['record' => $other->getRouteKey()])
        ->assertFormFieldEnabled('discount_percent')
        ->assertActionVisible(DeleteAction::class);

    $second = Sale::factory()->create();
    Livewire::test(ListSales::class)
        ->callTableBulkAction('delete', [$documented->id, $second->id])
        ->assertHasNoErrors();

    expect(Sale::find($documented->id))->not->toBeNull()
        ->and(Sale::find($second->id))->toBeNull();
});

it('shows the missing-document filter only in external-manual mode', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $admin->company->update(['invoicing_mode' => InvoicingMode::ReceiptOnly]);
    Livewire::test(ListSales::class)->assertTableFilterHidden('missing_fiscal_document');

    $admin->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]);
    Livewire::test(ListSales::class)->assertTableFilterVisible('missing_fiscal_document');
});
