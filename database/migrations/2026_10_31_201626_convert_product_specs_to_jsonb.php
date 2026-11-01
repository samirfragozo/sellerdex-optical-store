<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Postgres `json` has no equality operator, so Filament's `select distinct products.*`
     * (supplier attach select) fails. `jsonb` compares fine; other drivers are unaffected.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table products alter column specs type jsonb using specs::jsonb');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table products alter column specs type json using specs::json');
        }
    }
};
