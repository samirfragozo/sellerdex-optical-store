<?php

// tests/Browser/PosLabMeasurementsTest.php

use App\Enums\FrameType;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensOrder;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends the measurements and the customer frame details to the lab order', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    $companyId = $seller->company_id;
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $companyId]);
    PaymentMethod::factory()->create(['company_id' => $companyId, 'is_active' => true]);
    Supplier::factory()->laboratory()->create(['company_id' => $companyId]);
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $companyId]);
    $type = LensType::factory()->create(['name' => 'Monofocal', 'company_id' => $companyId]);
    $technology = LensTechnology::factory()->create(['name' => 'Estándar', 'company_id' => $companyId]);
    $material = LensMaterial::factory()->create(['name' => 'CR-39', 'company_id' => $companyId]);
    LensCombination::factory()->create([
        'company_id' => $companyId,
        'lens_type_id' => $type->id, 'lens_technology_id' => $technology->id, 'lens_material_id' => $material->id,
    ]);
    $customer = Customer::factory()->create(['company_id' => $companyId, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);
    $rx = Prescription::factory()->create(['company_id' => $companyId, 'customer_id' => $customer->id]);

    $this->actingAs($seller);

    visit('/pos')
        ->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->click('Usar existente')
        ->select('#prescription_id', (string) $rx->id)
        ->click('Continuar al lente')
        ->click('button[aria-pressed]:has-text("Monofocal")')
        ->click('button[aria-pressed]:has-text("Estándar")')
        ->click('button[aria-pressed]:has-text("CR-39")')
        ->click('button:has-text("Continuar a la montura")')
        ->click('text=El cliente trae su montura')
        ->fill('#own_frame_description', 'Montura dorada')
        ->fill('#own_frame_condition', 'Rayón leve')
        ->select('#frame_type', 'semi_rimless')
        ->fill('#od_height', '18')
        ->fill('#os_height', '18.5')
        ->fill('#frame_a', '52')
        ->fill('#frame_b', '38')
        ->fill('#frame_dbl', '18')
        ->click('button:has-text("Continuar al combo")')
        ->click('button:has-text("Guardar ítem")')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    $order = LensOrder::sole();
    expect($order->frame_type)->toBe(FrameType::SemiRimless)
        ->and($order->od_height)->toBe('18.0')
        ->and($order->os_height)->toBe('18.5')
        ->and($order->frame_dbl)->toBe('18.0')
        ->and($order->customer_frame_description)->toBe('Montura dorada')
        ->and($order->customer_frame_condition)->toBe('Rayón leve');
});
