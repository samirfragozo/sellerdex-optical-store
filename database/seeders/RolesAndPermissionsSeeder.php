<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Shield generates permissions in the `Action:Subject` format (pascal case, ':' separator).
     */
    public const ACTIONS = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny',
        'ForceDelete', 'ForceDeleteAny', 'Restore', 'RestoreAny', 'Reorder', 'Replicate',
    ];

    public const SUBJECTS = [
        'Customer', 'Expense', 'ExpenseCategory', 'LensOrder', 'Payment', 'PaymentMethod',
        'Prescription', 'Product', 'ProductCategory', 'PurchaseOrder', 'Role', 'Sale', 'Supplier', 'User',
    ];

    /**
     * Seller permissions: manage customers, prescriptions, sales, and payments (no deletes),
     * and read-only access to the product catalog.
     * Sellers cannot delete sales, nor update or delete payments.
     *
     * @var list<string>
     */
    public const SELLER_PERMISSIONS = [
        'ViewAny:Customer', 'View:Customer', 'Create:Customer', 'Update:Customer',
        'ViewAny:Prescription', 'View:Prescription', 'Create:Prescription', 'Update:Prescription',
        'ViewAny:Product', 'View:Product',
        'ViewAny:Sale', 'View:Sale', 'Create:Sale', 'Update:Sale',
        'ViewAny:Payment', 'View:Payment', 'Create:Payment',
        'ViewAny:LensOrder', 'View:LensOrder', 'Create:LensOrder', 'Update:LensOrder',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Bulk upsert instead of one findOrCreate() per permission (168 calls,
        // 2 queries each): this seeder runs before every single test via
        // tests/Pest.php's beforeEach, so the per-row query loop multiplied
        // into six figures of redundant queries across the suite.
        $now = now();
        $rows = [];
        foreach (self::SUBJECTS as $subject) {
            foreach (self::ACTIONS as $action) {
                $rows[] = ['name' => "{$action}:{$subject}", 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        Permission::query()->upsert($rows, ['name', 'guard_name'], ['updated_at']);

        Role::findOrCreate(User::ROLE_SUPERADMIN, 'web');
    }
}
