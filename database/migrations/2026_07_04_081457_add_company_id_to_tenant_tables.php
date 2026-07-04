<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string> */
    private array $tables = [
        'customers', 'products', 'product_categories', 'payment_methods',
        'expense_categories', 'expenses', 'sales', 'sale_items', 'payments',
        'prescriptions', 'lens_orders', 'suppliers', 'purchase_orders',
        'purchase_order_items', 'cash_closes',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('company_id')->nullable()->after('id')
                    ->constrained('companies')->nullOnDelete();
            });
        }

        // Assign all existing rows to the default company (id=1)
        $defaultCompanyId = DB::table('companies')->value('id');
        if ($defaultCompanyId) {
            foreach ($this->tables as $table) {
                DB::table($table)->whereNull('company_id')
                    ->update(['company_id' => $defaultCompanyId]);
            }
        }

        // Convert global unique constraints to per-company composite uniques
        Schema::table('sales', function (Blueprint $t) {
            $t->dropUnique('sales_number_unique');
            $t->unique(['number', 'company_id']);
        });

        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique('customers_id_number_unique');
            $t->unique(['id_number', 'company_id']);
        });

        Schema::table('product_categories', function (Blueprint $t) {
            $t->dropUnique('product_categories_key_unique');
            $t->dropUnique('product_categories_name_unique');
            $t->unique(['key', 'company_id']);
            $t->unique(['name', 'company_id']);
        });

        Schema::table('expense_categories', function (Blueprint $t) {
            $t->dropUnique('expense_categories_name_unique');
            $t->unique(['name', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $t) {
            $t->dropUnique(['name', 'company_id']);
            $t->unique('name');
        });

        Schema::table('product_categories', function (Blueprint $t) {
            $t->dropUnique(['name', 'company_id']);
            $t->dropUnique(['key', 'company_id']);
            $t->unique('name');
            $t->unique('key');
        });

        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique(['id_number', 'company_id']);
            $t->unique('id_number');
        });

        Schema::table('sales', function (Blueprint $t) {
            $t->dropUnique(['number', 'company_id']);
            $t->unique('number');
        });

        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeignIdFor(Company::class);
                $t->dropColumn('company_id');
            });
        }
    }
};
