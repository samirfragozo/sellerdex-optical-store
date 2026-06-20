<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Arriendo', 'Salario', 'Lentes terminados', 'Exámenes', 'Digitales', 'Otros'] as $name) {
            ExpenseCategory::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
