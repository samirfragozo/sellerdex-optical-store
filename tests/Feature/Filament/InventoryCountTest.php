<?php

use App\Enums\StockMovementType;
use App\Filament\Pages\InventoryCount;
use App\Filament\Resources\BusinessSettings\Pages\ManageBusinessSetting;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockLedger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->company = $this->admin->company;
});

it('asks for an initial count after the shop turns inventory on, and keeps the history when it turns it off', function () {
    $product = Product::factory()->create(['is_stockable' => true, 'stock' => 3]);
    StockLedger::record($product, StockMovementType::Adjustment, 1, reason: 'antes');

    Livewire::test(ManageBusinessSetting::class)->fillForm(['tracks_inventory' => false])->call('save')->assertHasNoFormErrors();
    expect(StockMovement::count())->toBe(1)
        ->and(collect($this->company->fresh()->saleReadiness())->pluck('key'))->not->toContain('inventory_initial_count');

    Livewire::test(ManageBusinessSetting::class)->fillForm(['tracks_inventory' => true])->call('save')->assertHasNoFormErrors();
    expect($this->company->fresh()->inventory_counted_at)->toBeNull()
        ->and(collect($this->company->fresh()->saleReadiness())->pluck('key'))->toContain('inventory_initial_count');
});

it('writes initial movements for the counted differences only', function () {
    $this->company->update(['inventory_counted_at' => null]);
    $counted = Product::factory()->create(['is_stockable' => true, 'stock' => 2]);
    $same = Product::factory()->create(['is_stockable' => true, 'stock' => 5]);
    $skipped = Product::factory()->create(['is_stockable' => true, 'stock' => 1]);
    $service = Product::factory()->create(['is_stockable' => false, 'stock' => null]);

    $page = Livewire::test(InventoryCount::class);
    $page->assertFormFieldExists("counts.{$counted->id}")
        ->assertFormFieldDoesNotExist("counts.{$service->id}")
        ->fillForm(['counts' => [$counted->id => 9, $same->id => 5, $skipped->id => null]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(StockMovement::sole())
        ->product_id->toBe($counted->id)
        ->type->toBe(StockMovementType::Initial)
        ->quantity->toBe(7)
        ->and($counted->fresh()->stock)->toBe(9)
        ->and($skipped->fresh()->stock)->toBe(1)
        ->and($this->company->fresh()->inventory_counted_at)->not->toBeNull()
        ->and(collect($this->company->fresh()->saleReadiness())->pluck('key'))->not->toContain('inventory_initial_count');
});

it('is not available when the shop does not track inventory', function () {
    $this->company->update(['tracks_inventory' => false]);

    $this->get(InventoryCount::getUrl())->assertForbidden();
});
