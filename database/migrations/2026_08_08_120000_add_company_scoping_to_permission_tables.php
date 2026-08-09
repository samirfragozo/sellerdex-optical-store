<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: on a fresh migrate, Spatie's base migration
        // (2026_06_08_184525_create_permission_tables.php) reads config('permission.teams')
        // live and already adds these columns/constraints itself now that teams is true,
        // so these blocks are no-ops there. On the real (pre-existing) DB, where that base
        // migration ran back when teams was false, these blocks do the actual work.
        if (! Schema::hasColumn('roles', 'company_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('model_has_roles', 'company_id')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('role_id')->index();
            });
        }

        if (! Schema::hasColumn('model_has_permissions', 'company_id')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('permission_id')->index();
            });
        }

        // Must happen before the backfill loop below, not after: the loop inserts
        // per-company ('admin'/'seller') rows while the old global rows (company_id
        // null) still exist, and the old unique(name, guard_name) constraint would
        // reject those inserts as duplicates of the old global row. Swapping to the
        // composite constraint first makes (company_id, name, guard_name) the
        // uniqueness key, so a per-company row and the old global row no longer collide.
        if (Schema::hasIndex('roles', 'roles_name_guard_name_unique')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique('roles_name_guard_name_unique');
            });
        }

        if (! Schema::hasIndex('roles', ['company_id', 'name', 'guard_name'], 'unique')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique(['company_id', 'name', 'guard_name']);
            });
        }

        $oldAdminRoleId = DB::table('roles')->where('name', 'admin')->whereNull('company_id')->value('id');
        $oldSellerRoleId = DB::table('roles')->where('name', 'seller')->whereNull('company_id')->value('id');

        $adminPermissionIds = $oldAdminRoleId
            ? DB::table('role_has_permissions')->where('role_id', $oldAdminRoleId)->pluck('permission_id')
            : collect();
        $sellerPermissionIds = $oldSellerRoleId
            ? DB::table('role_has_permissions')->where('role_id', $oldSellerRoleId)->pluck('permission_id')
            : collect();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $adminRoleId = DB::table('roles')->insertGetId([
                'company_id' => $companyId, 'name' => 'admin', 'guard_name' => 'web',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($adminPermissionIds as $permissionId) {
                DB::table('role_has_permissions')->insert(['role_id' => $adminRoleId, 'permission_id' => $permissionId]);
            }

            $sellerRoleId = DB::table('roles')->insertGetId([
                'company_id' => $companyId, 'name' => 'seller', 'guard_name' => 'web',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($sellerPermissionIds as $permissionId) {
                DB::table('role_has_permissions')->insert(['role_id' => $sellerRoleId, 'permission_id' => $permissionId]);
            }

            $companyUserIds = DB::table('users')->where('company_id', $companyId)->pluck('id');

            if ($oldAdminRoleId) {
                DB::table('model_has_roles')
                    ->where('role_id', $oldAdminRoleId)
                    ->whereIn('model_id', $companyUserIds)
                    ->update(['role_id' => $adminRoleId, 'company_id' => $companyId]);
            }
            if ($oldSellerRoleId) {
                DB::table('model_has_roles')
                    ->where('role_id', $oldSellerRoleId)
                    ->whereIn('model_id', $companyUserIds)
                    ->update(['role_id' => $sellerRoleId, 'company_id' => $companyId]);
            }
        }

        if ($oldAdminRoleId) {
            DB::table('role_has_permissions')->where('role_id', $oldAdminRoleId)->delete();
            DB::table('roles')->where('id', $oldAdminRoleId)->delete();
        }
        if ($oldSellerRoleId) {
            DB::table('role_has_permissions')->where('role_id', $oldSellerRoleId)->delete();
            DB::table('roles')->where('id', $oldSellerRoleId)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name', 'guard_name']);
            $table->unique(['name', 'guard_name']);
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
