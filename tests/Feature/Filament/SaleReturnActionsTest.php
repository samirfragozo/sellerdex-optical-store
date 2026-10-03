<?php

use App\Actions\RegisterSaleReturn;
use App\Enums\SaleDocumentType;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Filament\Resources\BusinessSettings\Pages\ManageBusinessSetting;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\RelationManagers\CreditsRelationManager;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Sales\RelationManagers\ReturnsRelationManager;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->cash = PaymentMethod::where('is_default', true)->first() ?? PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    PaymentMethod::storeCreditFor($this->admin->company_id) ?? PaymentMethod::factory()->create(['name' => 'Saldo a favor', 'is_store_credit' => true]);
    $this->customer = Customer::factory()->create();
    $this->sale = Sale::factory()->create(['customer_id' => $this->customer->id, 'discount_percent' => 0, 'surcharge_percent' => 0]);
    $this->line = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'product_id' => Product::factory()->create(['is_stockable' => true, 'stock' => 3])->id, 'quantity' => 2, 'unit_price' => 50_000, 'tax_rate' => 0]);
    Payment::factory()->create(['sale_id' => $this->sale->id, 'payment_method_id' => $this->cash->id, 'amount' => 100_000]);
});

it('returns a line to store credit from the sale page', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('returnItems'), [
            'lines' => [['sale_item_id' => $this->line->id, 'quantity' => 1, 'restock' => true]],
            'reason' => 'Cambio de modelo', 'refund_amount' => 0, 'store_credit_amount' => 50_000,
        ])
        ->assertHasNoActionErrors();

    expect($this->customer->creditBalance())->toBe(50_000);

    Livewire::test(ReturnsRelationManager::class, ['ownerRecord' => $this->sale, 'pageClass' => EditSale::class])
        ->assertCanSeeTableRecords($this->sale->returns);

    Livewire::test(CreditsRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => EditCustomer::class])
        ->assertCanSeeTableRecords($this->customer->credits)
        ->assertSee('50.000');
});

it('drops lines with zero units and refuses a return with none', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('returnItems'), [
            'lines' => [['sale_item_id' => $this->line->id, 'quantity' => 0, 'restock' => false]],
            'reason' => 'x', 'refund_amount' => 0, 'store_credit_amount' => 0,
        ])
        ->assertHasActionErrors(['lines']);

    expect(SaleReturn::count())->toBe(0);
});

it('shows the money limit as a form error instead of failing', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('valueAdjustment'), [
            'amount' => 10_000, 'reason' => 'x', 'refund_amount' => 0, 'store_credit_amount' => 20_000,
        ])
        ->assertHasActionErrors(['store_credit_amount']);
});

it('maps a per-line quantity error onto the repeater row', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('returnItems'), [
            'lines' => ['row-a' => ['sale_item_id' => $this->line->id, 'quantity' => 5, 'restock' => false]],
            'reason' => 'x', 'refund_amount' => 0, 'store_credit_amount' => 0,
        ])
        ->assertHasActionErrors(['lines.row-a.quantity']);
});

it('adjusts the value of a sale from the page', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('valueAdjustment'), [
            'amount' => 10_000, 'reason' => 'Descuento tardío', 'refund_amount' => 0, 'store_credit_amount' => 10_000,
        ])
        ->assertHasNoActionErrors();

    expect($this->sale->fresh()->netTotal())->toBe(90_000)
        ->and($this->customer->creditBalance())->toBe(10_000);
});

it('voids the sale from the page and hides void once voided', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('voidSale'), ['reason' => 'Error', 'refund_amount' => 0, 'store_credit_amount' => 100_000])
        ->assertHasNoActionErrors();

    expect($this->sale->fresh()->status)->toBe(SaleStatus::Voided);

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])->assertActionHidden('voidSale');
});

it('voids a sale from the sales table row', function () {
    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('voidSale')->table($this->sale), ['reason' => 'Error', 'refund_amount' => 0, 'store_credit_amount' => 100_000])
        ->assertHasNoActionErrors();

    expect($this->sale->fresh()->status)->toBe(SaleStatus::Voided);
});

it('labels the void action as a plan separe cancellation for layaways', function () {
    $layaway = Sale::factory()->create(['customer_id' => $this->customer->id, 'document_type' => SaleDocumentType::Layaway]);

    Livewire::test(EditSale::class, ['record' => $layaway->getRouteKey()])
        ->assertActionVisible('voidSale')
        ->assertSee(__('app.sale_return.actions.cancel_layaway'));

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->assertSee(__('app.sale_return.actions.void'))
        ->assertDontSee(__('app.sale_return.actions.cancel_layaway'));
});

it('hides void on a delivered sale', function () {
    $this->sale->update(['is_delivered' => true, 'delivered_at' => now()]);

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])->assertActionHidden('voidSale');
});

it('keeps the post-sale actions away from sellers', function () {
    $this->actingAs(User::factory()->seller()->create(['company_id' => $this->admin->company_id]));

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->assertActionHidden('returnItems')
        ->assertActionHidden('valueAdjustment')
        ->assertActionHidden('voidSale');
});

it('shows each customer credit balance in the customers list', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('valueAdjustment'), [
            'amount' => 10_000, 'reason' => 'x', 'refund_amount' => 0, 'store_credit_amount' => 10_000,
        ]);

    Livewire::test(ListCustomers::class)
        ->assertTableColumnStateSet('credit_balance', 10_000, $this->customer);
});

it('saves the layaway cancellation fee in the business settings', function () {
    Livewire::test(ManageBusinessSetting::class)
        ->fillForm(['layaway_cancellation_fee_percent' => 10])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $this->admin->company->fresh()->layaway_cancellation_fee_percent)->toBe(10.0);
});

it('locks the sale items once the sale has a return', function () {
    $manager = fn () => Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $this->sale->fresh(), 'pageClass' => EditSale::class]);

    $manager()->assertActionVisible(TestAction::make(CreateAction::class)->table())
        ->assertActionVisible(TestAction::make(EditAction::class)->table($this->line))
        ->assertActionVisible(TestAction::make(DeleteAction::class)->table($this->line));

    app(RegisterSaleReturn::class)->handle($this->sale, SaleReturnType::ValueAdjustment, ['amount' => 1_000, 'reason' => 'x'], $this->admin);

    $manager()->assertActionHidden(TestAction::make(CreateAction::class)->table())
        ->assertActionHidden(TestAction::make(EditAction::class)->table($this->line))
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($this->line));
});

it('locks the sale items on a voided sale', function () {
    app(RegisterSaleReturn::class)->handle($this->sale, SaleReturnType::Void, ['reason' => 'x', 'store_credit_amount' => 100_000], $this->admin);

    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $this->sale->fresh(), 'pageClass' => EditSale::class])
        ->assertActionHidden(TestAction::make(CreateAction::class)->table())
        ->assertActionHidden(TestAction::make(EditAction::class)->table($this->line));
});

it('never lets the money cap grow past what was paid when the net value is negative', function () {
    $handle = fn (array $data) => app(RegisterSaleReturn::class)->handle($this->sale->fresh(), SaleReturnType::Return, $data, $this->admin);

    $handle(['reason' => 'x', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 1]]]);
    // Simulate the sale shrinking below what was already returned.
    DB::table('sales')->where('id', $this->sale->id)->update(['total' => 30_000]);

    expect(fn () => $handle(['reason' => 'x', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 1]], 'store_credit_amount' => 110_000]))
        ->toThrow(ValidationException::class);
});

it('shows the money limit under the refund on a walk-in sale', function () {
    $walkIn = Sale::factory()->create(['customer_id' => null, 'discount_percent' => 0, 'surcharge_percent' => 0]);
    $line = SaleItem::factory()->create(['sale_id' => $walkIn->id, 'quantity' => 2, 'unit_price' => 50_000, 'tax_rate' => 0]);
    Payment::factory()->create(['sale_id' => $walkIn->id, 'payment_method_id' => $this->cash->id, 'amount' => 60_000]);

    Livewire::test(EditSale::class, ['record' => $walkIn->getRouteKey()])
        ->callAction(TestAction::make('returnItems'), [
            'lines' => [['sale_item_id' => $line->id, 'quantity' => 1, 'restock' => false]],
            'reason' => 'x', 'refund_amount' => 50_000, 'refund_payment_method_id' => $this->cash->id,
        ])
        ->assertHasActionErrors(['refund_amount' => __('app.sale_return.money_exceeds', ['max' => '10.000'])]);
});

it('hides return items when nothing is left to return', function () {
    app(RegisterSaleReturn::class)->handle($this->sale, SaleReturnType::Return, ['reason' => 'x', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 2]]], $this->admin);

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->assertActionHidden('returnItems')
        ->assertActionVisible('valueAdjustment');
});

it('hides every post-sale action on a quote', function () {
    $quote = Sale::factory()->create(['customer_id' => $this->customer->id, 'document_type' => SaleDocumentType::Quote]);
    SaleItem::factory()->create(['sale_id' => $quote->id, 'quantity' => 1, 'unit_price' => 50_000, 'tax_rate' => 0]);

    Livewire::test(EditSale::class, ['record' => $quote->getRouteKey()])
        ->assertActionHidden('returnItems')
        ->assertActionHidden('valueAdjustment')
        ->assertActionHidden('voidSale');
});
