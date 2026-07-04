<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(
            ['slug' => 'mi-optica'],
            ['name' => 'Mi Óptica', 'is_active' => true, 'plan' => 'free'],
        );
    }
}
