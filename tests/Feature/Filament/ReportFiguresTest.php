<?php

use App\Actions\RegisterSaleReturn;
use App\Enums\CashCloseType;
use App\Enums\SaleReturnType;
use App\Filament\Widgets\FinancialSummaryWidget;
use App\Filament\Widgets\SellerPerformanceWidget;
use App\Filament\Widgets\TodaySummaryWidget;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\CashCloseService;
use Livewire\Livewire;

/**
 * Today: a 100k sale paid in cash with one unit (50k) returned to store credit, a 30k sale paid by transfer and
 * voided with a refund, and an 80k sale with 20k paid. Net sales 130k, real money in 120k, 60k still owed.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->cash = PaymentMethod::where('is_default', true)->first() ?? PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    PaymentMethod::storeCreditFor($this->admin->company_id) ?? PaymentMethod::factory()->create(['name' => 'Saldo a favor', 'is_store_credit' => true]);
    $transfer = PaymentMethod::factory()->create(['name' => 'Transferencia', 'is_default' => false]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['is_stockable' => false]);

    $makeSale = function (int $units, int $unitPrice, int $paid, PaymentMethod $method) use ($customer, $product): Sale {
        $sale = Sale::factory()->create(['customer_id' => $customer->id, 'seller_id' => $this->admin->id, 'discount_percent' => 0, 'surcharge_percent' => 0]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => $units, 'unit_price' => $unitPrice, 'tax_rate' => 0]);
        $sale->recalculateTotals();
        Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $method->id, 'amount' => $paid]);

        return $sale->fresh();
    };

    $returned = $makeSale(2, 50_000, 100_000, $this->cash);
    app(RegisterSaleReturn::class)->handle($returned, SaleReturnType::Return, [
        'reason' => 'x', 'items' => [['sale_item_id' => $returned->items()->sole()->id, 'quantity' => 1]],
        'refund_amount' => 0, 'store_credit_amount' => 50_000,
    ], $this->admin);

    $voided = $makeSale(1, 30_000, 30_000, $transfer);
    app(RegisterSaleReturn::class)->handle($voided, SaleReturnType::Void, [
        'reason' => 'x', 'refund_amount' => 30_000, 'refund_payment_method_id' => $transfer->id, 'store_credit_amount' => 0,
    ], $this->admin);

    $makeSale(1, 80_000, 20_000, $this->cash);
});

it('shows net sales, real money collected and the net receivable on the today widget', function () {
    Livewire::test(TodaySummaryWidget::class)
        ->assertSee('$130,000')
        ->assertSee('$120,000')
        ->assertSee('$60,000');
});

it('shows net sales and real money collected on the financial widget', function () {
    Livewire::test(FinancialSummaryWidget::class, ['pageFilters' => ['from' => now()->toDateString(), 'to' => now()->toDateString()]])
        ->assertSee('$130,000')
        ->assertSee('$120,000')
        ->assertDontSee('$180,000');
});

it('counts net sales without voided ones on the seller performance widget', function () {
    Livewire::test(SellerPerformanceWidget::class, ['pageFilters' => ['from' => now()->toDateString(), 'to' => now()->toDateString()]])
        ->assertTableColumnStateSet('sales_total', 130_000, $this->admin)
        ->assertTableColumnStateSet('sales_count', 2, $this->admin);
});

it('closes the cash with net sales and real money, keeping store credit visible by method', function () {
    $snap = app(CashCloseService::class)->compute(CashCloseType::Daily, now());
    $credit = PaymentMethod::storeCreditFor($this->admin->company_id);

    expect($snap['total_sales'])->toBe(130_000)
        ->and($snap['total_collected'])->toBe(120_000)
        ->and($snap['total_receivable'])->toBe(60_000)
        ->and($snap['collected_by_method'][$credit->id])->toBe(-50_000);
});
