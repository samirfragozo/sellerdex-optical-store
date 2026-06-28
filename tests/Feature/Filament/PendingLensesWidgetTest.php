<?php

use App\Enums\LensOrderStatus;
use App\Filament\Widgets\PendingLensesWidget;
use App\Models\LensOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lista los laboratorios con lentes pendientes y su conteo', function () {
    $this->actingAs(User::factory()->admin()->create());

    $labA = Supplier::factory()->laboratory()->create(['name' => 'Lab A']);
    $labB = Supplier::factory()->laboratory()->create(['name' => 'Lab B']);

    LensOrder::factory()->count(2)->create(['supplier_id' => $labA->id, 'lab_status' => LensOrderStatus::Sent->value]);
    LensOrder::factory()->received()->create(['supplier_id' => $labA->id]); // not pending
    LensOrder::factory()->received()->create(['supplier_id' => $labB->id]); // lab B has no pending

    Livewire::test(PendingLensesWidget::class)
        ->assertCanSeeTableRecords([$labA])
        ->assertCanNotSeeTableRecords([$labB])
        ->assertTableColumnStateSet('pending_count', 2, record: $labA);
});

it('excluye proveedores que no son laboratorio aunque tengan lentes pendientes', function () {
    $this->actingAs(User::factory()->admin()->create());

    $supplier = Supplier::factory()->create(['is_laboratory' => false]);
    LensOrder::factory()->create(['supplier_id' => $supplier->id, 'lab_status' => LensOrderStatus::Sent->value]);

    Livewire::test(PendingLensesWidget::class)
        ->assertCanNotSeeTableRecords([$supplier]);
});

it('el vendedor puede ver el widget', function () {
    $this->actingAs(User::factory()->seller()->create());

    $lab = Supplier::factory()->laboratory()->create(['name' => 'Lab A']);
    LensOrder::factory()->create(['supplier_id' => $lab->id, 'lab_status' => LensOrderStatus::Sent->value]);

    Livewire::test(PendingLensesWidget::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$lab]);
});
