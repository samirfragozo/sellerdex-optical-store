<?php

use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('las categorías de gasto son exclusivas del admin', function () {
    expect(User::factory()->seller()->create()->can('ViewAny:ExpenseCategory'))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('ViewAny:ExpenseCategory'))->toBeTrue();
});

it('siembra las categorías de gasto base sin duplicar', function () {
    $this->seed(ExpenseCategorySeeder::class);
    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::pluck('name')->all())
        ->toContain('Arriendo', 'Salario', 'Lentes Terminados', 'Exámenes', 'Digitales', 'Otros')
        ->and(ExpenseCategory::where('name', 'Arriendo')->count())->toBe(1);
});
