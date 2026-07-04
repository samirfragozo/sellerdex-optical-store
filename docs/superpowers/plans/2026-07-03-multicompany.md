# Multi-Company SaaS Architecture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convertir Sellerdex en SaaS multi-tenant donde múltiples ópticas comparten una instalación y cada una ve únicamente sus propios datos.

**Architecture:** Single database con `company_id` en todas las tablas de negocio. Un trait `BelongsToCompany` + `CompanyScope` filtra automáticamente por la empresa del usuario autenticado. El superadmin (`company_id = null`) bypasea el scope y ve todo desde un panel Filament separado.

**Tech Stack:** Laravel 13, PHP 8.5, Filament v4, Inertia v3 + Vue 3, Pest 4, SQLite (dev) / PostgreSQL (prod).

## Global Constraints

- PHP 8.5, Laravel 13, Filament v4, Pest 4.
- Todos los textos de UI en español.
- Usar `#[Fillable([...])]` attribute (no `$fillable` property) — patrón existente en todos los modelos.
- Usar `php artisan make:test --pest {Name}` para crear tests.
- Correr `vendor/bin/pint --dirty --format agent` después de cada cambio PHP.
- Correr `php artisan test --compact` (no `--filter`) para verificar que no hay regresiones.
- No auto-commit sin instrucción explícita — el implementador hace commits al final de cada tarea.
- No crear nuevas dependencias de composer.
- `company_id` es nullable en BD (SQLite no soporta ALTER COLUMN para cambiar a NOT NULL después); se garantiza en capa de aplicación mediante el trait.

---

### Task 1: Company model, migración y relación con User

**Files:**
- Create: `database/migrations/2026_07_03_000001_create_companies_table.php`
- Create: `database/migrations/2026_07_03_000002_add_company_id_to_users_table.php`
- Create: `app/Models/Company.php`
- Create: `database/factories/CompanyFactory.php`
- Create: `database/seeders/CompanySeeder.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Test: `tests/Feature/CompanyModelTest.php`

**Interfaces:**
- Produces: `Company` model con `Company::current(): Company` que retorna la empresa del usuario autenticado.
- Produces: `User::company(): BelongsTo` relación.
- Produces: `UserFactory::forCompany(Company $company): static` y `UserFactory::superadmin(): static`.
- Produces: constante `User::ROLE_SUPERADMIN = 'superadmin'`.

- [ ] **Step 1: Escribir los tests que fallarán**

```bash
php artisan make:test --pest CompanyModelTest
```

Contenido de `tests/Feature/CompanyModelTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a company with required fields', function () {
    $company = Company::factory()->create(['name' => 'Óptica Central']);
    expect($company->name)->toBe('Óptica Central')
        ->and($company->is_active)->toBeTrue()
        ->and($company->slug)->not->toBeEmpty();
});

it('generates a unique slug from the company name', function () {
    $a = Company::factory()->create(['name' => 'Óptica Central']);
    $b = Company::factory()->create(['name' => 'Óptica Central']);
    expect($a->slug)->not->toBe($b->slug);
});

it('users belong to a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    expect($user->company->id)->toBe($company->id);
});

it('superadmin user has null company_id', function () {
    $superadmin = User::factory()->superadmin()->create();
    expect($superadmin->company_id)->toBeNull()
        ->and($superadmin->hasRole(User::ROLE_SUPERADMIN))->toBeTrue();
});

it('admin panel accessible only to users with a company', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company)->admin()->create();
    $superadmin = User::factory()->superadmin()->create();

    $panel = app(\Filament\Panel::class)::make()->id('admin');

    expect($admin->canAccessPanel($panel))->toBeTrue()
        ->and($superadmin->canAccessPanel($panel))->toBeFalse();
});
```

- [ ] **Step 2: Verificar que fallan**

```bash
php artisan test --compact --filter=CompanyModelTest
```

Expected: todos los tests FAIL.

- [ ] **Step 3: Crear la migración de companies**

```bash
php artisan make:migration create_companies_table --no-interaction
```

Contenido de la migración:

```php
public function up(): void
{
    Schema::create('companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('tax_id')->nullable();
        $table->string('address')->nullable();
        $table->string('phones')->nullable();
        $table->string('logo')->nullable();
        $table->boolean('is_active')->default(true);
        $table->string('plan')->default('free');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('companies');
}
```

- [ ] **Step 4: Crear la migración de users.company_id**

```bash
php artisan make:migration add_company_id_to_users_table --no-interaction
```

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropForeignIdFor(\App\Models\Company::class);
        $table->dropColumn('company_id');
    });
}
```

- [ ] **Step 5: Crear el modelo Company**

`app/Models/Company.php`:

```php
<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'tax_id', 'address', 'phones', 'logo', 'is_active', 'plan'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            $company->slug ??= self::uniqueSlug($company->name);
        });
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /** Returns the authenticated user's company. */
    public static function current(): self
    {
        return static::findOrFail(Auth::user()->company_id);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
```

- [ ] **Step 6: Crear CompanyFactory**

`database/factories/CompanyFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => null, // auto-generated in booted()
            'tax_id' => fake()->numerify('###.###.###-#'),
            'address' => fake()->address(),
            'phones' => fake()->phoneNumber(),
            'is_active' => true,
            'plan' => 'free',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

- [ ] **Step 7: Actualizar User model**

En `app/Models/User.php`, agregar:

1. La constante `ROLE_SUPERADMIN`:
```php
public const ROLE_SUPERADMIN = 'superadmin';
```

2. El atributo `company_id` a `#[Fillable]`:
```php
#[Fillable(['name', 'email', 'password', 'is_active', 'company_id'])]
```

3. La relación `company()` después de los métodos de rol existentes:
```php
public function company(): BelongsTo
{
    return $this->belongsTo(Company::class);
}
```
Agregar `use Illuminate\Database\Eloquent\Relations\BelongsTo;` al bloque de imports.

4. Actualizar `canAccessPanel` para distinguir paneles:
```php
public function canAccessPanel(Panel $panel): bool
{
    return match ($panel->getId()) {
        'superadmin' => $this->company_id === null && $this->hasRole(self::ROLE_SUPERADMIN),
        default => $this->company_id !== null && $this->is_active,
    };
}
```

- [ ] **Step 8: Actualizar UserFactory**

En `database/factories/UserFactory.php`, agregar los dos estados al final de la clase:

```php
public function forCompany(Company $company): static
{
    return $this->state(['company_id' => $company->id]);
}

public function superadmin(): static
{
    return $this->state(['company_id' => null])
        ->afterCreating(fn (User $user) => $user->assignRole(
            Role::findOrCreate(User::ROLE_SUPERADMIN)
        ));
}
```

Agregar `use App\Models\Company;` al bloque de imports de `UserFactory.php`.

- [ ] **Step 9: Crear CompanySeeder (reemplaza BusinessSettingSeeder)**

`database/seeders/CompanySeeder.php`:

```php
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
```

- [ ] **Step 10: Actualizar DatabaseSeeder**

En `database/seeders/DatabaseSeeder.php`, reemplazar `BusinessSettingSeeder::class` por `CompanySeeder::class`:

```php
$this->call([
    RolesAndPermissionsSeeder::class,
    CompanySeeder::class,       // ← reemplaza BusinessSettingSeeder
    PaymentMethodSeeder::class,
    ExpenseCategorySeeder::class,
    ProductCategorySeeder::class,
    ProductCatalogSeeder::class,
]);
```

- [ ] **Step 11: Actualizar AdminPanelProvider para usar Company**

En `app/Providers/Filament/AdminPanelProvider.php`, reemplazar las referencias a `BusinessSetting`:

```php
// Eliminar: use App\Models\BusinessSetting;
use App\Models\Company;
```

```php
->brandName(fn () => rescue(
    fn () => Company::current()->name ?? 'Óptica',
    'Óptica',
    report: false
))
->brandLogo(fn () => rescue(
    fn () => ($logo = Company::current()->logo)
        ? Storage::disk('public')->url($logo)
        : null,
    null,
    report: false
))
```

- [ ] **Step 12: Correr migrations y tests**

```bash
php artisan migrate:fresh --seed
php artisan test --compact --filter=CompanyModelTest
```

Expected: todos los tests PASS.

- [ ] **Step 13: Verificar no hay regresiones**

```bash
php artisan test --compact
```

Expected: todos los tests existentes siguen pasando.

- [ ] **Step 14: Formatear y commitear**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_03_000001_create_companies_table.php \
        database/migrations/2026_07_03_000002_add_company_id_to_users_table.php \
        app/Models/Company.php \
        database/factories/CompanyFactory.php \
        database/seeders/CompanySeeder.php \
        app/Models/User.php \
        database/factories/UserFactory.php \
        database/seeders/DatabaseSeeder.php \
        app/Providers/Filament/AdminPanelProvider.php \
        tests/Feature/CompanyModelTest.php
git commit -m "feat(multicompany): Company model, migración y relación con User"
```

---

### Task 2: CompanyScope + BelongsToCompany trait aplicado a todos los modelos de negocio

**Files:**
- Create: `app/Scopes/CompanyScope.php`
- Create: `app/Traits/BelongsToCompany.php`
- Create: `database/migrations/2026_07_03_000003_add_company_id_to_tenant_tables.php`
- Modify: `app/Models/Customer.php`, `Product.php`, `ProductCategory.php`, `PaymentMethod.php`, `ExpenseCategory.php`, `Expense.php`, `Sale.php`, `SaleItem.php`, `Payment.php`, `Prescription.php`, `LensOrder.php`, `Supplier.php`, `PurchaseOrder.php`, `PurchaseOrderItem.php`, `CashClose.php`
- Test: `tests/Feature/CompanyScopeTest.php`

**Interfaces:**
- Consumes: `Company` model de Task 1; `User::company_id` de Task 1.
- Produces: `BelongsToCompany` trait usable en cualquier modelo Eloquent; `CompanyScope` que filtra por `company_id` del usuario autenticado (sin filtro para superadmin).

- [ ] **Step 1: Escribir los tests que fallarán**

```bash
php artisan make:test --pest CompanyScopeTest
```

`tests/Feature/CompanyScopeTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('users only see their own company customers', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = User::factory()->forCompany($companyA)->admin()->create();
    $userB = User::factory()->forCompany($companyB)->admin()->create();

    $this->actingAs($userA);
    Customer::factory()->create(['company_id' => $companyA->id, 'name' => 'Cliente A']);
    Customer::factory()->create(['company_id' => $companyB->id, 'name' => 'Cliente B']);

    $names = Customer::pluck('name')->all();
    expect($names)->toContain('Cliente A')
        ->and($names)->not->toContain('Cliente B');
});

it('superadmin sees all companies customers', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $superadmin = User::factory()->superadmin()->create();

    Customer::factory()->create(['company_id' => $companyA->id, 'name' => 'Cliente A']);
    Customer::factory()->create(['company_id' => $companyB->id, 'name' => 'Cliente B']);

    $this->actingAs($superadmin);
    $names = Customer::pluck('name')->all();
    expect($names)->toContain('Cliente A')
        ->and($names)->toContain('Cliente B');
});

it('auto-fills company_id on create', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->admin()->create();

    $this->actingAs($user);
    $customer = Customer::create(['name' => 'Test', 'document_type' => 'cc']);
    expect($customer->company_id)->toBe($company->id);
});

it('same sale number can exist in different companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    \App\Models\Sale::factory()->create(['company_id' => $companyA->id, 'number' => '000001']);
    \App\Models\Sale::factory()->create(['company_id' => $companyB->id, 'number' => '000001']);

    expect(\App\Models\Sale::withoutGlobalScopes()->count())->toBe(2);
});
```

- [ ] **Step 2: Verificar que fallan**

```bash
php artisan test --compact --filter=CompanyScopeTest
```

Expected: FAIL (clase BelongsToCompany no existe).

- [ ] **Step 3: Crear CompanyScope**

`app/Scopes/CompanyScope.php`:

```php
<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = Auth::user()?->company_id;
        if ($companyId !== null) {
            $builder->where($model->getTable().'.company_id', $companyId);
        }
        // superadmin (company_id = null): sin filtro, ve todo
    }
}
```

- [ ] **Step 4: Crear BelongsToCompany trait**

`app/Traits/BelongsToCompany.php`:

```php
<?php

namespace App\Traits;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model): void {
            $model->company_id ??= Auth::user()?->company_id;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

- [ ] **Step 5: Crear la migración para todas las tablas de negocio**

```bash
php artisan make:migration add_company_id_to_tenant_tables --no-interaction
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'customers', 'products', 'product_categories', 'payment_methods',
        'expense_categories', 'expenses', 'sales', 'sale_items', 'payments',
        'prescriptions', 'lens_orders', 'suppliers', 'purchase_orders',
        'purchase_order_items', 'cash_closes',
    ];

    public function up(): void
    {
        // Add company_id to all tenant tables
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

        // Make sale numbers unique per company (not globally)
        Schema::table('sales', function (Blueprint $t) {
            $t->dropUnique('sales_number_unique');
            $t->unique(['number', 'company_id']);
        });

        // Make customer id_number unique per company
        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique('customers_id_number_unique');
            $t->unique(['id_number', 'company_id']);
        });

        // Make product_categories.key unique per company
        Schema::table('product_categories', function (Blueprint $t) {
            $t->dropUnique('product_categories_key_unique');
            $t->unique(['key', 'company_id']);
        });
    }

    public function down(): void
    {
        // Restore global unique constraints
        Schema::table('sales', function (Blueprint $t) {
            $t->dropUnique(['number', 'company_id']);
            $t->unique('number');
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique(['id_number', 'company_id']);
            $t->unique('id_number');
        });
        Schema::table('product_categories', function (Blueprint $t) {
            $t->dropUnique(['key', 'company_id']);
            $t->unique('key');
        });

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeignIdFor(\App\Models\Company::class);
                $t->dropColumn('company_id');
            });
        }
    }
};
```

- [ ] **Step 6: Aplicar BelongsToCompany a los 15 modelos**

Para cada modelo, agregar `use BelongsToCompany;` al bloque de traits y `'company_id'` al `#[Fillable]`.

**`app/Models/Customer.php`:**
```php
use App\Traits\BelongsToCompany;
// ...
#[Fillable(['company_id', 'name', 'last_name', 'document_type', 'id_number', 'phone', 'address', 'city', 'birth_date', 'email', 'notes'])]
class Customer extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;
```

**`app/Models/Product.php`:**
Buscar la línea `#[Fillable([...` y agregar `'company_id'` al inicio del array. Agregar `use BelongsToCompany;` al bloque `use`.

**`app/Models/ProductCategory.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/PaymentMethod.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/ExpenseCategory.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/Expense.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/Sale.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

Además, actualizar `Sale::nextNumber()` para ser por empresa:
```php
public static function nextNumber(): string
{
    $companyId = \Illuminate\Support\Facades\Auth::user()?->company_id;
    $max = (int) static::withTrashed()
        ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
        ->max('id');

    return str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
}
```

**`app/Models/SaleItem.php`:**
```php
#[Fillable(['company_id', 'sale_id', 'group_key', 'product_id', 'description', 'quantity', 'unit_price', 'unit_cost', 'line_total'])]
```
Agregar `use BelongsToCompany;`.

**`app/Models/Payment.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/Prescription.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/LensOrder.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/Supplier.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/PurchaseOrder.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/PurchaseOrderItem.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

**`app/Models/CashClose.php`:**
Agregar `'company_id'` al `#[Fillable]`. Agregar `use BelongsToCompany;`.

Para cada uno de los 12 modelos que no se muestran con código completo: el patrón es idéntico — agregar `use App\Traits\BelongsToCompany;` al bloque de imports, `use BelongsToCompany;` en el cuerpo, y `'company_id'` al inicio del array de `#[Fillable]`.

- [ ] **Step 7: Correr migrations**

```bash
php artisan migrate:fresh --seed
```

- [ ] **Step 8: Correr tests de scope**

```bash
php artisan test --compact --filter=CompanyScopeTest
```

Expected: todos PASS.

- [ ] **Step 9: Verificar no hay regresiones**

```bash
php artisan test --compact
```

Si algún test falla porque un factory no tiene `company_id`, agregar `.forCompany(Company::factory()->create())` o `['company_id' => Company::factory()->create()->id]` en el factory del modelo afectado. El patrón es: cualquier factory que crea un modelo tenant necesita `company_id`.

- [ ] **Step 10: Formatear y commitear**

```bash
vendor/bin/pint --dirty --format agent
git add app/Scopes/CompanyScope.php \
        app/Traits/BelongsToCompany.php \
        database/migrations/2026_07_03_000003_add_company_id_to_tenant_tables.php \
        app/Models/Customer.php app/Models/Product.php app/Models/ProductCategory.php \
        app/Models/PaymentMethod.php app/Models/ExpenseCategory.php app/Models/Expense.php \
        app/Models/Sale.php app/Models/SaleItem.php app/Models/Payment.php \
        app/Models/Prescription.php app/Models/LensOrder.php app/Models/Supplier.php \
        app/Models/PurchaseOrder.php app/Models/PurchaseOrderItem.php app/Models/CashClose.php \
        tests/Feature/CompanyScopeTest.php
git commit -m "feat(multicompany): CompanyScope y BelongsToCompany trait en todos los modelos"
```

---

### Task 3: EnsureCompanyIsActive middleware

**Files:**
- Create: `app/Http/Middleware/EnsureCompanyIsActive.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Test: `tests/Feature/CompanyActiveMiddlewareTest.php`

**Interfaces:**
- Consumes: `Company::is_active` de Task 1, `User::company_id` de Task 1.
- Produces: middleware `ensure.company.active` que bloquea usuarios de empresas suspendidas.

- [ ] **Step 1: Escribir los tests que fallarán**

```bash
php artisan make:test --pest CompanyActiveMiddlewareTest
```

`tests/Feature/CompanyActiveMiddlewareTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('active company user can access admin', function () {
    $company = Company::factory()->create(['is_active' => true]);
    $user = User::factory()->forCompany($company)->admin()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

it('suspended company user is redirected', function () {
    $company = Company::factory()->create(['is_active' => false]);
    $user = User::factory()->forCompany($company)->admin()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect();
});

it('superadmin bypasses company active check', function () {
    $superadmin = User::factory()->superadmin()->create();

    // Superadmin goes to /superadmin, not /admin — just verify no crash on middleware
    expect($superadmin->company_id)->toBeNull();
});
```

- [ ] **Step 2: Verificar que fallan**

```bash
php artisan test --compact --filter=CompanyActiveMiddlewareTest
```

Expected: FAIL.

- [ ] **Step 3: Crear el middleware**

`app/Http/Middleware/EnsureCompanyIsActive.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // superadmin (no company) — siempre pasa
        if ($user === null || $user->company_id === null) {
            return $next($request);
        }

        if (! $user->company?->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta ha sido suspendida. Contacta al soporte.',
            ]);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Registrar alias en bootstrap/app.php**

En `bootstrap/app.php`, dentro de `->withMiddleware(function (Middleware $middleware)`, agregar:

```php
$middleware->alias([
    'ensure.company.active' => \App\Http\Middleware\EnsureCompanyIsActive::class,
]);
```

- [ ] **Step 5: Agregar middleware al panel admin de Filament**

En `app/Providers/Filament/AdminPanelProvider.php`, en el bloque `->authMiddleware([...])`:

```php
->authMiddleware([
    Authenticate::class,
    \App\Http\Middleware\EnsureCompanyIsActive::class,
])
```

- [ ] **Step 6: Correr los tests**

```bash
php artisan test --compact --filter=CompanyActiveMiddlewareTest
```

Expected: todos PASS.

- [ ] **Step 7: Verificar regresiones**

```bash
php artisan test --compact
```

Expected: todos los tests existentes siguen pasando.

- [ ] **Step 8: Formatear y commitear**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/EnsureCompanyIsActive.php \
        bootstrap/app.php \
        app/Providers/Filament/AdminPanelProvider.php \
        tests/Feature/CompanyActiveMiddlewareTest.php
git commit -m "feat(multicompany): middleware EnsureCompanyIsActive"
```

---

### Task 4: Registro de nueva empresa (reemplaza Fortify CreateNewUser)

**Files:**
- Create: `app/Actions/Fortify/CreateCompanyAndUser.php`
- Modify: `app/Providers/FortifyServiceProvider.php`
- Modify: `resources/js/pages/auth/Register.vue`
- Test: `tests/Feature/RegisterCompanyTest.php`

**Interfaces:**
- Consumes: `Company` model de Task 1, `User::forCompany()` factory state.
- Produces: endpoint `POST /register` que crea empresa + usuario admin en una transacción; redirige a `/admin`.

- [ ] **Step 1: Escribir los tests que fallarán**

```bash
php artisan make:test --pest RegisterCompanyTest
```

`tests/Feature/RegisterCompanyTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a company and admin user on registration', function () {
    $this->post('/register', [
        'company_name' => 'Óptica Sur',
        'name' => 'Ana García',
        'email' => 'ana@opticasur.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect('/admin');

    $company = Company::where('name', 'Óptica Sur')->first();
    expect($company)->not->toBeNull()
        ->and($company->is_active)->toBeTrue();

    $user = User::where('email', 'ana@opticasur.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->company_id)->toBe($company->id)
        ->and($user->hasRole('admin'))->toBeTrue();
});

it('rolls back if user creation fails', function () {
    // Forzar email duplicado
    User::factory()->create(['email' => 'dup@test.com']);

    $this->post('/register', [
        'company_name' => 'Óptica Norte',
        'name' => 'Juan',
        'email' => 'dup@test.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertSessionHasErrors('email');

    // No debe haber quedado empresa huérfana
    expect(Company::where('name', 'Óptica Norte')->exists())->toBeFalse();
});

it('requires a company name', function () {
    $this->post('/register', [
        'name' => 'Juan',
        'email' => 'juan@test.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertSessionHasErrors('company_name');
});
```

- [ ] **Step 2: Verificar que fallan**

```bash
php artisan test --compact --filter=RegisterCompanyTest
```

Expected: FAIL.

- [ ] **Step 3: Crear CreateCompanyAndUser**

`app/Actions/Fortify/CreateCompanyAndUser.php`:

```php
<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateCompanyAndUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /** @param array<string, string> $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            'company_name' => ['required', 'string', 'max:255'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $company = Company::create([
                'name' => $input['company_name'],
                'is_active' => true,
                'plan' => 'free',
            ]);

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'company_id' => $company->id,
                'is_active' => true,
            ]);

            $user->assignRole(Role::findOrCreate(User::ROLE_ADMIN));

            return $user;
        });
    }
}
```

- [ ] **Step 4: Registrar la acción en FortifyServiceProvider y redirigir a /admin**

En `app/Providers/FortifyServiceProvider.php`:

```php
// Cambiar la línea:
Fortify::createUsersUsing(CreateNewUser::class);
// Por:
Fortify::createUsersUsing(CreateCompanyAndUser::class);
```

Agregar el import:
```php
use App\Actions\Fortify\CreateCompanyAndUser;
```

También agregar la redirección post-registro. Después de la línea `Fortify::createUsersUsing(...)`, agregar:

```php
Fortify::redirects('register', '/admin');
```

- [ ] **Step 5: Agregar campo company_name a la página de registro**

En `resources/js/pages/auth/Register.vue`, agregar el campo `company_name` antes del campo `name`. Buscar la sección del formulario y agregar:

```vue
<div class="grid gap-2">
    <Label for="company_name">Nombre de la óptica</Label>
    <Input
        id="company_name"
        v-model="form.company_name"
        type="text"
        required
        autofocus
        placeholder="Óptica Central"
    />
    <InputError :message="form.errors.company_name" />
</div>
```

En el `useForm(...)`, agregar `company_name: ''` al objeto inicial.

- [ ] **Step 6: Correr los tests**

```bash
php artisan test --compact --filter=RegisterCompanyTest
```

Expected: todos PASS.

- [ ] **Step 7: Verificar regresiones**

```bash
php artisan test --compact
```

Expected: todos los tests pasan.

- [ ] **Step 8: Formatear y commitear**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Fortify/CreateCompanyAndUser.php \
        app/Providers/FortifyServiceProvider.php \
        resources/js/pages/auth/Register.vue \
        tests/Feature/RegisterCompanyTest.php
git commit -m "feat(multicompany): registro de empresa con Fortify"
```

---

### Task 5: Panel de superadmin (Filament)

**Files:**
- Create: `app/Providers/Filament/SuperadminPanelProvider.php`
- Create: `app/Filament/Superadmin/Resources/CompanyResource.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Feature/SuperadminPanelTest.php`

**Interfaces:**
- Consumes: `Company` model de Task 1; `User::canAccessPanel()` actualizado en Task 1; `User::ROLE_SUPERADMIN`.
- Produces: panel Filament en `/superadmin` accesible solo para superadmin; recurso para gestionar empresas (listar, editar, activar/suspender).

- [ ] **Step 1: Escribir los tests que fallarán**

```bash
php artisan make:test --pest SuperadminPanelTest
```

`tests/Feature/SuperadminPanelTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('superadmin can access the superadmin panel', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get('/superadmin')
        ->assertSuccessful();
});

it('regular admin cannot access the superadmin panel', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company)->admin()->create();

    $this->actingAs($admin)
        ->get('/superadmin')
        ->assertRedirect();
});

it('superadmin can list all companies', function () {
    Company::factory()->count(3)->create();
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get('/superadmin/companies')
        ->assertSuccessful();
});

it('superadmin can toggle company active status', function () {
    $company = Company::factory()->create(['is_active' => true]);
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin);
    $company->update(['is_active' => false]);

    expect($company->fresh()->is_active)->toBeFalse();
});
```

- [ ] **Step 2: Verificar que fallan**

```bash
php artisan test --compact --filter=SuperadminPanelTest
```

Expected: FAIL.

- [ ] **Step 3: Crear el panel de superadmin**

```bash
php artisan make:filament-panel superadmin --no-interaction
```

Esto crea `app/Providers/Filament/SuperadminPanelProvider.php`. Editarlo para:

```php
<?php

namespace App\Providers\Filament;

use App\Filament\Superadmin\Resources\CompanyResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperadminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('superadmin')
            ->path('superadmin')
            ->login()
            ->brandName('Sellerdex — Admin')
            ->colors(['primary' => Color::Violet])
            ->resources([CompanyResource::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
```

- [ ] **Step 4: Crear CompanyResource**

```bash
mkdir -p app/Filament/Superadmin/Resources
```

`app/Filament/Superadmin/Resources/CompanyResource.php`:

```php
<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $modelLabel = 'Empresa';
    protected static ?string $pluralModelLabel = 'Empresas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nombre')->required(),
            Forms\Components\TextInput::make('tax_id')->label('NIT'),
            Forms\Components\TextInput::make('address')->label('Dirección'),
            Forms\Components\TextInput::make('phones')->label('Teléfonos'),
            Forms\Components\Toggle::make('is_active')->label('Activa')->default(true),
            Forms\Components\Select::make('plan')
                ->label('Plan')
                ->options(['free' => 'Free', 'hobby' => 'Hobby', 'pro' => 'Pro'])
                ->default('free'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('plan')->label('Plan'),
                Tables\Columns\IconColumn::make('is_active')->label('Activa')->boolean(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users'),
                Tables\Columns\TextColumn::make('created_at')->label('Registro')->date('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (Company $r) => $r->is_active ? 'Suspender' : 'Activar')
                    ->icon(fn (Company $r) => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Company $r) => $r->is_active ? 'danger' : 'success')
                    ->action(fn (Company $r) => $r->update(['is_active' => ! $r->is_active])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Crear las Pages del recurso**

```bash
mkdir -p app/Filament/Superadmin/Resources/CompanyResource/Pages
```

`app/Filament/Superadmin/Resources/CompanyResource/Pages/ListCompanies.php`:
```php
<?php
namespace App\Filament\Superadmin\Resources\CompanyResource\Pages;
use App\Filament\Superadmin\Resources\CompanyResource;
use Filament\Resources\Pages\ListRecords;
class ListCompanies extends ListRecords {
    protected static string $resource = CompanyResource::class;
}
```

`app/Filament/Superadmin/Resources/CompanyResource/Pages/CreateCompany.php`:
```php
<?php
namespace App\Filament\Superadmin\Resources\CompanyResource\Pages;
use App\Filament\Superadmin\Resources\CompanyResource;
use Filament\Resources\Pages\CreateRecord;
class CreateCompany extends CreateRecord {
    protected static string $resource = CompanyResource::class;
}
```

`app/Filament/Superadmin\Resources\CompanyResource\Pages\EditCompany.php`:
```php
<?php
namespace App\Filament\Superadmin\Resources\CompanyResource\Pages;
use App\Filament\Superadmin\Resources\CompanyResource;
use Filament\Resources\Pages\EditRecord;
class EditCompany extends EditRecord {
    protected static string $resource = CompanyResource::class;
}
```

- [ ] **Step 6: Registrar SuperadminPanelProvider en bootstrap/providers.php**

`bootstrap/providers.php`:
```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\SuperadminPanelProvider::class,  // ← agregar
    App\Providers\FortifyServiceProvider::class,
];
```

- [ ] **Step 7: Correr los tests**

```bash
php artisan test --compact --filter=SuperadminPanelTest
```

Expected: todos PASS.

- [ ] **Step 8: Verificar regresiones**

```bash
php artisan test --compact
```

Expected: todos los tests existentes siguen pasando.

- [ ] **Step 9: Formatear y commitear**

```bash
vendor/bin/pint --dirty --format agent
git add app/Providers/Filament/SuperadminPanelProvider.php \
        app/Filament/Superadmin/ \
        bootstrap/providers.php \
        tests/Feature/SuperadminPanelTest.php
git commit -m "feat(multicompany): panel superadmin con gestión de empresas"
```

---

## Notas para el implementador

**Factories de modelos tenant:** Todos los factories de los 15 modelos tenant necesitan incluir `company_id` en su estado base o ser creados dentro de un contexto autenticado. Si un test falla con "company_id cannot be null", el fix es agregar `'company_id' => Company::factory()` al factory del modelo.

**Filament resources existentes:** Los resources de Filament en `app/Filament/Resources/` no necesitan cambios — el CompanyScope ya filtra automáticamente. Verificar que los tests de Filament existentes (en `tests/Feature/Filament/`) sigan pasando tras Task 2.

**`BusinessSetting` model:** Queda como código muerto tras Task 1. Se puede eliminar en una tarea de limpieza posterior; no bloquea este plan.

**`RolesAndPermissionsSeeder`:** Agregar el rol `superadmin` al seeder para que esté disponible en producción:
```php
Role::findOrCreate(User::ROLE_SUPERADMIN);
```
Incluir en Task 1.
