<?php

use App\Models\Customer;
use App\Models\KitSlot;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ReferenceKit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../Support/GoldenCatalog.php';

uses(RefreshDatabase::class);

it('lets the seller pick combo slot products for an armado', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    $golden = goldenCatalog($seller);
    ReferenceKit::installFor($seller->company);
    openCashRegisterSession($seller);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'is_active' => true]);
    Supplier::factory()->laboratory()->create(['company_id' => $seller->company_id]);
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $seller->company_id]);
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);
    $lens = $golden['lens'];
    $slotId = fn (string $key): int => KitSlot::query()->whereHas('slotCategory', fn ($q) => $q->where('key', $key))->value('id');

    visit('/pos')
        ->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->fill('#rx_exam_date', now()->toDateString())
        ->select('#rx_lens_type', 'single_vision')
        ->click('Continuar al lente')
        ->click('button:has-text("'.$lens->lensType->name.'")')
        ->click('button:has-text("'.$lens->lensTechnology->name.'")')
        ->click('button:has-text("'.$lens->lensMaterial->name.'")')
        ->click('button:has-text("Continuar a la montura")')
        ->click('text=El cliente trae su montura')
        ->click('button:has-text("Continuar al combo")')
        ->select('#kit_slot_'.$slotId('case'), 'Estuche grande')
        ->check('#kit_slot_'.$slotId('service').'_selected')
        ->click('button:has-text("Guardar ítem")')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    $sale = Sale::query()->latest('id')->firstOrFail();
    $skus = $sale->items()->with('product')->get()->pluck('product.sku');
    $lensLine = $sale->items()->whereHas('lensConfig')->firstOrFail();

    expect($skus)->toContain('ACC-ESTUCHE-LARGE')
        ->and((int) $lensLine->unit_price)->toBe(200_000);
});
