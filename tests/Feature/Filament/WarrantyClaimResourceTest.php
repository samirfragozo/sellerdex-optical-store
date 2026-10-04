<?php

use App\Enums\WarrantyClaimStatus;
use App\Filament\Resources\WarrantyClaims\Pages\CreateWarrantyClaim;
use App\Filament\Resources\WarrantyClaims\Pages\ListWarrantyClaims;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WarrantyClaim;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->seller()->create());
    $this->sale = Sale::factory()->create(['is_delivered' => true, 'delivered_at' => today()->subDays(10)]);
    $this->item = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'quantity' => 1]);
});

it('lets a seller register a claim for a delivered line', function () {
    Livewire::test(CreateWarrantyClaim::class)
        ->fillForm(['sale_id' => $this->sale->id, 'sale_item_id' => $this->item->id, 'type' => 'warranty', 'received_at' => today()->toDateString(), 'customer_description' => 'Se soltó el lente'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WarrantyClaim::count())->toBe(1);
});

it('shows a form error for an out-of-term claim', function () {
    $this->sale->update(['delivered_at' => today()->subYears(3)]);

    Livewire::test(CreateWarrantyClaim::class)
        ->fillForm(['sale_id' => $this->sale->id, 'sale_item_id' => $this->item->id, 'type' => 'warranty', 'received_at' => today()->toDateString(), 'customer_description' => 'Se soltó el lente'])
        ->call('create')
        ->assertHasFormErrors(['sale_item_id']);

    expect(WarrantyClaim::count())->toBe(0);
});

it('starts the review from the list', function () {
    $claim = WarrantyClaim::factory()->create(['sale_item_id' => $this->item->id]);

    Livewire::test(ListWarrantyClaims::class)->callTableAction('startReview', $claim);

    expect($claim->fresh()->status)->toBe(WarrantyClaimStatus::InReview);
});
