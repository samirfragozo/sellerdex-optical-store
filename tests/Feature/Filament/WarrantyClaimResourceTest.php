<?php

use App\Enums\WarrantyClaimStatus;
use App\Filament\Resources\WarrantyClaims\Pages\CreateWarrantyClaim;
use App\Filament\Resources\WarrantyClaims\Pages\ListWarrantyClaims;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WarrantyClaim;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Forms\Components\Select;
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

it('finds a delivered sale older than the 200 most recent by its number', function () {
    $oldest = Sale::factory()->create(['is_delivered' => true, 'delivered_at' => today()->subDays(400)]);
    Sale::factory()->count(200)->create(['is_delivered' => true, 'delivered_at' => today()]);
    Sale::factory()->create(['is_delivered' => false, 'number' => $oldest->number.'X']);

    Livewire::test(CreateWarrantyClaim::class)
        ->assertFormFieldExists('sale_id', fn (Select $field): bool => array_keys($field->getSearchResults($oldest->number)) === [$oldest->id]
            && $field->getSearchResults('no-such-sale') === []);
});

it('resolves a claim from the list with an admin PIN', function () {
    $admin = User::factory()->admin()->create(['company_id' => $this->sale->company_id, 'approval_pin' => '4321']);
    $claim = WarrantyClaim::factory()->create(['sale_item_id' => $this->item->id, 'status' => WarrantyClaimStatus::InReview]);

    Livewire::test(ListWarrantyClaims::class)
        ->callTableAction('resolve', $claim, ['resolution' => 'repair', 'approval_pin' => '4321'])
        ->assertHasNoTableActionErrors();

    expect($claim->fresh())->status->toBe(WarrantyClaimStatus::Resolved)->resolved_by->toBe($admin->id);
});

it('refuses a wrong PIN when resolving', function () {
    User::factory()->admin()->create(['company_id' => $this->sale->company_id, 'approval_pin' => '4321']);
    $claim = WarrantyClaim::factory()->create(['sale_item_id' => $this->item->id, 'status' => WarrantyClaimStatus::InReview]);

    Livewire::test(ListWarrantyClaims::class)
        ->callTableAction('resolve', $claim, ['resolution' => 'repair', 'approval_pin' => '0000'])
        ->assertHasTableActionErrors(['approval_pin']);

    expect($claim->fresh()->status)->toBe(WarrantyClaimStatus::InReview);
});

it('delivers a resolved claim', function () {
    $claim = WarrantyClaim::factory()->create(['sale_item_id' => $this->item->id, 'status' => WarrantyClaimStatus::Resolved]);

    Livewire::test(ListWarrantyClaims::class)->callTableAction('deliver', $claim);

    expect($claim->fresh()->status)->toBe(WarrantyClaimStatus::Delivered);
});

it('shows a refund above what is due under the refund field on a walk-in sale', function () {
    User::factory()->admin()->create(['company_id' => $this->sale->company_id, 'approval_pin' => '4321']);
    $this->sale->forceFill(['customer_id' => null])->saveQuietly();
    $method = PaymentMethod::factory()->create(['company_id' => $this->sale->company_id]);
    $claim = WarrantyClaim::factory()->create(['sale_item_id' => $this->item->id, 'status' => WarrantyClaimStatus::InReview]);

    Livewire::test(ListWarrantyClaims::class)
        ->callTableAction('resolve', $claim, ['resolution' => 'refund', 'refund_amount' => 999_999, 'refund_payment_method_id' => $method->id, 'approval_pin' => '4321'])
        ->assertHasTableActionErrors(['refund_amount']);

    expect($claim->fresh()->status)->toBe(WarrantyClaimStatus::InReview);
});
