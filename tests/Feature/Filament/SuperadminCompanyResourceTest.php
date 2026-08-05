<?php

use App\Filament\Superadmin\Resources\CompanyResource\Pages\CreateCompany;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('seeds default reference data when the superadmin creates a company', function () {
    $this->actingAs(User::factory()->superadmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));

    Livewire::test(CreateCompany::class)
        ->fillForm(['name' => 'Óptica Norte'])
        ->call('create')
        ->assertHasNoFormErrors();

    $company = Company::where('name', 'Óptica Norte')->firstOrFail();

    expect(PaymentMethod::where('company_id', $company->id)->where('name', 'Efectivo')->exists())->toBeTrue()
        ->and(ProductCategory::where('company_id', $company->id)->count())->toBe(4)
        ->and(ExpenseCategory::where('company_id', $company->id)->count())->toBe(6);
});
