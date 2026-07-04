<?php

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('siembra los datos base del negocio', function () {
    $this->seed(DatabaseSeeder::class);

    expect(PaymentMethod::where('is_default', true)->where('name', 'Efectivo')->exists())->toBeTrue()
        ->and(ExpenseCategory::count())->toBe(6)
        ->and(Company::count())->toBe(1)
        ->and(User::where('email', 'admin@optica.test')->first()?->hasRole('admin'))->toBeTrue();
});
