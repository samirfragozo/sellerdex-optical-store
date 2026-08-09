<?php

use App\Filament\Widgets\SellerPerformanceWidget;
use App\Models\Company;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('only lists sellers from the current company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $adminA = User::factory()->forCompany($companyA)->admin()->create();
    $sellerA = User::factory()->forCompany($companyA)->seller()->create();
    $sellerB = User::factory()->forCompany($companyB)->seller()->create();

    Sale::factory()->create([
        'company_id' => $companyA->id,
        'seller_id' => $sellerA->id,
        'sold_at' => '2026-06-15',
    ]);
    Sale::factory()->create([
        'company_id' => $companyB->id,
        'seller_id' => $sellerB->id,
        'sold_at' => '2026-06-15',
    ]);

    $this->actingAs($adminA);

    $filters = ['from' => '2026-06-01', 'to' => '2026-06-30'];

    Livewire::test(SellerPerformanceWidget::class, ['pageFilters' => $filters])
        ->assertCanSeeTableRecords([$sellerA])
        ->assertCanNotSeeTableRecords([$sellerB]);
});
