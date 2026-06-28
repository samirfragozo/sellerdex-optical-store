<?php

use App\Enums\SaleStatus;
use App\Filament\Widgets\SalesByCategoryWidget;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('agrega unidades y monto de ventas por categoría dentro del período', function () {
    $this->actingAs(User::factory()->admin()->create());

    $lens = ProductCategory::factory()->create(['name' => 'Lente', 'key' => 'lens']);
    $frame = ProductCategory::factory()->create(['name' => 'Montura', 'key' => 'frame']);
    $lensProduct = Product::factory()->create(['product_category_id' => $lens->id]);
    $frameProduct = Product::factory()->create(['product_category_id' => $frame->id]);

    $inPeriod = Sale::factory()->create(['sold_at' => '2026-06-15', 'status' => SaleStatus::Paid->value]);
    SaleItem::factory()->create(['sale_id' => $inPeriod->id, 'product_id' => $lensProduct->id, 'quantity' => 2, 'unit_price' => 100000]);
    SaleItem::factory()->create(['sale_id' => $inPeriod->id, 'product_id' => $frameProduct->id, 'quantity' => 1, 'unit_price' => 50000]);

    // Excluded: voided sale and out-of-period sale.
    $voided = Sale::factory()->create(['sold_at' => '2026-06-16', 'status' => SaleStatus::Voided->value]);
    SaleItem::factory()->create(['sale_id' => $voided->id, 'product_id' => $lensProduct->id, 'quantity' => 5, 'unit_price' => 100000]);

    $outOfPeriod = Sale::factory()->create(['sold_at' => '2026-05-01', 'status' => SaleStatus::Paid->value]);
    SaleItem::factory()->create(['sale_id' => $outOfPeriod->id, 'product_id' => $lensProduct->id, 'quantity' => 9, 'unit_price' => 100000]);

    $filters = ['from' => '2026-06-01', 'to' => '2026-06-30'];

    Livewire::test(SalesByCategoryWidget::class, ['pageFilters' => $filters])
        ->assertCanSeeTableRecords([$lens, $frame])
        ->assertTableColumnStateSet('units', 2, record: $lens)
        ->assertTableColumnStateSet('amount', 200000, record: $lens)
        ->assertTableColumnStateSet('units', 1, record: $frame)
        ->assertTableColumnStateSet('amount', 50000, record: $frame);
});
