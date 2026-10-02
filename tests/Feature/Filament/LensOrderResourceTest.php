<?php

use App\Enums\LensOrderStatus;
use App\Filament\Resources\LensOrders\LensOrderResource;
use App\Filament\Resources\LensOrders\Pages\ListLensOrders;
use App\Models\Customer;
use App\Models\LensOrder;
use App\Models\SaleItemLensConfig;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('permite a un vendedor ver las órdenes de laboratorio', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get(LensOrderResource::getUrl())
        ->assertSuccessful();
});

it('la pestaña pendientes filtra las órdenes no recibidas', function () {
    $this->actingAs(User::factory()->admin()->create());

    $pending = LensOrder::factory()->create(['lab_status' => LensOrderStatus::Sent->value]);
    $received = LensOrder::factory()->received()->create();

    Livewire::test(ListLensOrders::class)
        ->set('activeTab', 'pendientes')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$received]);
});

it('shows the armado patient on the lens orders list', function () {
    $this->actingAs(User::factory()->admin()->create());
    $patient = Customer::factory()->create(['name' => 'Luis', 'last_name' => 'Pérez']);
    $config = SaleItemLensConfig::factory()->create(['patient_id' => $patient->id]);
    LensOrder::factory()->create(['sale_item_id' => $config->sale_item_id]);

    $this->get(LensOrderResource::getUrl())->assertSee('Luis Pérez');
});
