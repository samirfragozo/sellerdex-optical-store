# Óptica — Plan 1: Cimientos (Fase 0) + Maestros (Fase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Montar los cimientos del sistema (Filament v4, roles, auditoría, settings) y todos los datos maestros del back-office (métodos de pago, categorías de gasto, clientes, productos, gastos, prescripciones) con control de acceso por rol.

**Architecture:** Backend Laravel 13 con panel Filament v4 en `/admin`. Roles vía enum `role` en `users` + Policies. Auditoría con `spatie/laravel-activitylog`. Esquema en inglés, UI en español. Montos en pesos enteros (COP). Modelos nuevos siguen el patrón del proyecto: atributos PHP 8 `#[Fillable([...])]`.

**Tech Stack:** Laravel 13, Filament v4, spatie/laravel-activitylog, Pest v4, SQLite.

**Spec:** `docs/superpowers/specs/2026-06-07-optica-management-system-design.md`

**Convenciones para todas las tareas:**
- TDD: test que falla → mínima implementación → test que pasa → commit.
- Tests con Pest. En cada archivo de feature test que toque la BD, primera línea: `uses(RefreshDatabase::class);`.
- Crear archivos con `php artisan make:*` (siempre `--no-interaction`); luego editar.
- Tras tocar PHP: `vendor/bin/pint --dirty --format agent` antes de commitear.
- Correr tests: `php artisan test --compact --filter=<NombreTest>`.

---

## File Structure

**Fase 0 — Cimientos**
- `app/Enums/UserRole.php` — enum de roles (`Admin`, `Seller`) + helpers de etiqueta.
- `app/Providers/Filament/AdminPanelProvider.php` — panel `/admin` (generado por Filament).
- `database/migrations/*_add_role_and_is_active_to_users_table.php`
- `app/Models/User.php` — añadir `role`, `is_active`, cast a enum, `canAccessPanel()`, helpers `isAdmin()`/`isSeller()`.
- `app/Models/BusinessSetting.php` + migración + seeder (singleton).
- `app/Support/Authorization/*` — no aplica; las Policies viven en `app/Policies/`.

**Fase 1 — Maestros** (cada recurso = modelo + migración + factory + policy + resource Filament)
- `app/Models/PaymentMethod.php`, `app/Policies/PaymentMethodPolicy.php`, `app/Filament/Resources/PaymentMethods/*`
- `app/Models/ExpenseCategory.php`, policy, resource
- `app/Models/Customer.php`, policy, resource
- `app/Models/Product.php` + `app/Enums/ProductCategory.php`, policy, resource
- `app/Models/Expense.php`, policy, resource
- `app/Models/Prescription.php` + `app/Enums/LensType.php`, policy, resource
- `database/seeders/*` — `PaymentMethodSeeder`, `ExpenseCategorySeeder`, `DatabaseSeeder` actualizado.

---

## Fase 0 — Cimientos

### Task 1: Instalar Filament v4 y crear el panel admin

**Files:**
- Create: `app/Providers/Filament/AdminPanelProvider.php` (generado)
- Modify: `composer.json`, `bootstrap/providers.php` (automático)

- [ ] **Step 1: Requerir Filament v4**

```bash
composer require filament/filament:"^4.0" -W
```
Expected: instala filament/filament y dependencias sin errores.

- [ ] **Step 2: Instalar el panel**

```bash
php artisan filament:install --panels --no-interaction
```
Expected: crea `app/Providers/Filament/AdminPanelProvider.php` con `->path('admin')` y lo registra en `bootstrap/providers.php`.

- [ ] **Step 3: Verificar que el panel responde**

```bash
php artisan route:list --path=admin | head -5
```
Expected: aparecen rutas bajo `admin/...` (p. ej. `admin/login`).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: install Filament v4 admin panel"
```

---

### Task 2: Instalar spatie/laravel-activitylog

**Files:**
- Modify: `composer.json`
- Create: `database/migrations/*_create_activity_log_table.php` (publicada)

- [ ] **Step 1: Requerir el paquete**

```bash
composer require spatie/laravel-activitylog
```

- [ ] **Step 2: Publicar y migrar**

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations" --no-interaction
php artisan migrate --no-interaction
```
Expected: tablas `activity_log` creadas.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: install spatie/laravel-activitylog"
```

---

### Task 3: Roles de usuario (enum + columnas + acceso al panel)

**Files:**
- Create: `app/Enums/UserRole.php`
- Create: `database/migrations/2026_06_07_000001_add_role_and_is_active_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/Auth/PanelAccessTest.php`

- [ ] **Step 1: Crear el enum de roles**

Create `app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Seller = 'seller';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Seller => 'Vendedor',
        };
    }
}
```

- [ ] **Step 2: Crear la migración**

```bash
php artisan make:migration add_role_and_is_active_to_users_table --no-interaction
```

Reemplazar el cuerpo con (renombrar el archivo a `2026_06_07_000001_...` si se desea orden estable):

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('seller')->after('email');
        $table->boolean('is_active')->default(true)->after('role');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['role', 'is_active']);
    });
}
```

- [ ] **Step 3: Escribir el test que falla**

Create `tests/Feature/Auth/PanelAccessTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('permite a un admin activo acceder al panel', function () {
    $user = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});

it('permite a un vendedor activo acceder al panel', function () {
    $user = User::factory()->create(['role' => UserRole::Seller, 'is_active' => true]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});

it('niega el acceso a un usuario inactivo', function () {
    $user = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('expone helpers de rol', function () {
    expect(User::factory()->create(['role' => UserRole::Admin])->isAdmin())->toBeTrue()
        ->and(User::factory()->create(['role' => UserRole::Seller])->isSeller())->toBeTrue();
});
```

- [ ] **Step 4: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=PanelAccessTest`
Expected: FAIL — `canAccessPanel`/`isAdmin` no existen, columna `role` desconocida.

- [ ] **Step 5: Actualizar el modelo User**

Modify `app/Models/User.php`:
- Añadir `role`, `is_active` al atributo `#[Fillable([...])]` existente.
- Implementar `FilamentUser` y añadir casts/helpers.

```php
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

// en el #[Fillable([...])] existente añadir: 'role', 'is_active'

class User extends Authenticatable implements PasskeyUser, FilamentUser
{
    // ... traits existentes ...

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
    }
}
```

- [ ] **Step 6: Actualizar el factory**

Modify `database/factories/UserFactory.php` — añadir al array de `definition()`:

```php
'role' => \App\Enums\UserRole::Seller,
'is_active' => true,
```

Y añadir estados al final de la clase:

```php
public function admin(): static
{
    return $this->state(fn () => ['role' => \App\Enums\UserRole::Admin]);
}

public function seller(): static
{
    return $this->state(fn () => ['role' => \App\Enums\UserRole::Seller]);
}
```

- [ ] **Step 7: Migrar y correr el test (debe pasar)**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=PanelAccessTest
```
Expected: PASS (4 tests).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add user roles and panel access control"
```

---

### Task 4: Política base reutilizable (regla anti-fraude "vendedor no elimina")

Crea un trait/clase base para no repetir la lógica de autorización en cada policy.

**Files:**
- Create: `app/Policies/Concerns/AdminManaged.php`
- Test: `tests/Feature/Policies/AdminManagedTest.php`

- [ ] **Step 1: Escribir el test que falla**

Create `tests/Feature/Policies/AdminManagedTest.php`:

```php
<?php

use App\Models\User;
use App\Policies\Concerns\AdminManaged;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$policy = fn () => new class
{
    use AdminManaged;
};

it('solo el admin puede eliminar', function () use ($policy) {
    $p = $policy();
    expect($p->delete(User::factory()->admin()->create()))->toBeTrue()
        ->and($p->delete(User::factory()->seller()->create()))->toBeFalse();
});

it('ambos roles pueden ver y crear por defecto', function () use ($policy) {
    $p = $policy();
    $seller = User::factory()->seller()->create();
    expect($p->viewAny($seller))->toBeTrue()
        ->and($p->create($seller))->toBeTrue();
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=AdminManagedTest`
Expected: FAIL — clase `AdminManaged` no existe.

- [ ] **Step 3: Implementar el trait**

Create `app/Policies/Concerns/AdminManaged.php`:

```php
<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Reglas por defecto: ambos roles ven y crean; solo el Admin
 * elimina, restaura, borra definitivamente y anula/edita registros sensibles.
 * Las policies concretas pueden sobreescribir cualquier método.
 */
trait AdminManaged
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user): bool
    {
        return true;
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php artisan test --compact --filter=AdminManagedTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add AdminManaged base policy (sellers cannot delete)"
```

---

### Task 5: BusinessSetting (singleton de datos del negocio)

**Files:**
- Create: `app/Models/BusinessSetting.php`
- Create: `database/migrations/2026_06_07_000002_create_business_settings_table.php`
- Create: `database/seeders/BusinessSettingSeeder.php`
- Test: `tests/Feature/BusinessSettingTest.php`

- [ ] **Step 1: Migración**

```bash
php artisan make:migration create_business_settings_table --no-interaction
```

Cuerpo `up()`:

```php
Schema::create('business_settings', function (Blueprint $table) {
    $table->id();
    $table->string('name')->default('Mi Óptica');
    $table->string('tax_id')->nullable();
    $table->string('address')->nullable();
    $table->string('phones')->nullable();
    $table->string('logo')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 2: Test que falla**

Create `tests/Feature/BusinessSettingTest.php`:

```php
<?php

use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devuelve siempre el mismo registro singleton', function () {
    $a = BusinessSetting::current();
    $b = BusinessSetting::current();

    expect($a->id)->toBe($b->id)
        ->and(BusinessSetting::count())->toBe(1);
});
```

- [ ] **Step 3: Correr (debe fallar)**

Run: `php artisan test --compact --filter=BusinessSettingTest`
Expected: FAIL — modelo/método `current` no existe.

- [ ] **Step 4: Modelo**

Create `app/Models/BusinessSetting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'tax_id', 'address', 'phones', 'logo'])]
class BusinessSetting extends Model
{
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
```

- [ ] **Step 5: Seeder**

Create `database/seeders/BusinessSettingSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use Illuminate\Database\Seeder;

class BusinessSettingSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::current();
    }
}
```

- [ ] **Step 6: Migrar + correr (debe pasar)**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=BusinessSettingTest
```
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add BusinessSetting singleton"
```

---

## Fase 1 — Maestros

### Task 6: PaymentMethod — modelo, migración, factory, seeder (Efectivo protegido)

**Files:**
- Create: `app/Models/PaymentMethod.php`
- Create: `database/migrations/2026_06_07_000010_create_payment_methods_table.php`
- Create: `database/factories/PaymentMethodFactory.php`
- Create: `database/seeders/PaymentMethodSeeder.php`
- Test: `tests/Feature/PaymentMethodTest.php`

- [ ] **Step 1: Migración**

```bash
php artisan make:migration create_payment_methods_table --no-interaction
```

Cuerpo `up()`:

```php
Schema::create('payment_methods', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_default')->default(false);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

- [ ] **Step 2: Modelo + factory**

```bash
php artisan make:model PaymentMethod --factory --no-interaction
```

Reemplazar `app/Models/PaymentMethod.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'is_active', 'is_default', 'sort_order'])]
class PaymentMethod extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\PaymentMethodFactory> */
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** El método por defecto (Efectivo) no se puede eliminar ni desactivar. */
    public function isProtected(): bool
    {
        return $this->is_default;
    }
}
```

`database/factories/PaymentMethodFactory.php` → `definition()`:

```php
return [
    'name' => fake()->unique()->word(),
    'is_active' => true,
    'is_default' => false,
    'sort_order' => 0,
];
```

- [ ] **Step 3: Test que falla**

Create `tests/Feature/PaymentMethodTest.php`:

```php
<?php

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('siembra Efectivo como método por defecto protegido', function () {
    $this->seed(\Database\Seeders\PaymentMethodSeeder::class);

    $cash = PaymentMethod::where('is_default', true)->first();

    expect($cash)->not->toBeNull()
        ->and($cash->name)->toBe('Efectivo')
        ->and($cash->is_active)->toBeTrue()
        ->and($cash->isProtected())->toBeTrue();
});

it('no duplica Efectivo si se siembra dos veces', function () {
    $this->seed(\Database\Seeders\PaymentMethodSeeder::class);
    $this->seed(\Database\Seeders\PaymentMethodSeeder::class);

    expect(PaymentMethod::where('name', 'Efectivo')->count())->toBe(1);
});
```

- [ ] **Step 4: Correr (debe fallar)**

Run: `php artisan test --compact --filter=PaymentMethodTest`
Expected: FAIL — seeder no existe.

- [ ] **Step 5: Seeder**

Create `database/seeders/PaymentMethodSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::updateOrCreate(
            ['name' => 'Efectivo'],
            ['is_active' => true, 'is_default' => true, 'sort_order' => 0],
        );
    }
}
```

- [ ] **Step 6: Migrar + correr (debe pasar)**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=PaymentMethodTest
```
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add PaymentMethod with protected default Cash"
```

---

### Task 7: PaymentMethod — Policy + Filament resource (Efectivo no editable/borrable)

**Files:**
- Create: `app/Policies/PaymentMethodPolicy.php`
- Create: `app/Filament/Resources/PaymentMethods/*` (generado)
- Test: `tests/Feature/PaymentMethodPolicyTest.php`

- [ ] **Step 1: Test que falla (policy)**

Create `tests/Feature/PaymentMethodPolicyTest.php`:

```php
<?php

use App\Models\PaymentMethod;
use App\Models\User;
use App\Policies\PaymentMethodPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('nadie puede eliminar el método por defecto; el admin sí los demás', function () {
    $policy = new PaymentMethodPolicy;
    $admin = User::factory()->admin()->create();
    $cash = PaymentMethod::factory()->create(['is_default' => true]);
    $other = PaymentMethod::factory()->create(['is_default' => false]);

    expect($policy->delete($admin, $cash))->toBeFalse()
        ->and($policy->delete($admin, $other))->toBeTrue();
});

it('el vendedor no gestiona métodos de pago', function () {
    $policy = new PaymentMethodPolicy;
    $seller = User::factory()->seller()->create();

    expect($policy->viewAny($seller))->toBeFalse()
        ->and($policy->create($seller))->toBeFalse();
});
```

- [ ] **Step 2: Correr (debe fallar)**

Run: `php artisan test --compact --filter=PaymentMethodPolicyTest`
Expected: FAIL — policy no existe.

- [ ] **Step 3: Implementar policy**

Create `app/Policies/PaymentMethodPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PaymentMethod $method): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PaymentMethod $method): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, PaymentMethod $method): bool
    {
        return $user->isAdmin() && ! $method->isProtected();
    }
}
```

- [ ] **Step 4: Correr (debe pasar)**

Run: `php artisan test --compact --filter=PaymentMethodPolicyTest`
Expected: PASS.

- [ ] **Step 5: Generar el resource Filament**

```bash
php artisan make:filament-resource PaymentMethod --generate --no-interaction
```
Expected: crea `app/Filament/Resources/PaymentMethods/...` con form y table introspectados del modelo.

- [ ] **Step 6: Ajustar etiquetas en español y proteger Efectivo**

En la clase `PaymentMethodResource` añadir:

```php
protected static ?string $modelLabel = 'Método de pago';
protected static ?string $pluralModelLabel = 'Métodos de pago';
protected static ?string $navigationLabel = 'Métodos de pago';
```

En el form (campo `is_default`): hacerlo de solo lectura para no crear dos defaults a mano — en el componente `Toggle::make('is_default')` añadir `->disabled()->dehydrated(false)`.

En el form, el campo `is_active`: deshabilitarlo cuando el registro está protegido:

```php
Toggle::make('is_active')
    ->disabled(fn (?PaymentMethod $record) => $record?->isProtected() === true);
```

En la tabla, la acción de borrado ya respeta la policy `delete()` (Efectivo no aparecerá borrable). Etiquetar columnas: `name` → "Nombre", `is_active` → "Activo".

- [ ] **Step 7: Smoke test del resource (página carga)**

Create `tests/Feature/Filament/PaymentMethodResourceTest.php`:

```php
<?php

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

it('el admin ve el listado de métodos de pago', function () {
    $this->actingAs(User::factory()->admin()->create());

    livewire(PaymentMethodResource\Pages\ListPaymentMethods::class)
        ->assertSuccessful();
})->skip(fn () => ! class_exists(PaymentMethodResource\Pages\ListPaymentMethods::class), 'Ajustar namespace de páginas al generado por Filament');
```

> Nota: el namespace exacto de las páginas lo define el generador de Filament v4. Ajustar el `use`/clase al que aparezca en `app/Filament/Resources/PaymentMethods/Pages/`.

- [ ] **Step 8: Correr suite + Pint + commit**

```bash
php artisan test --compact --filter=PaymentMethod
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: PaymentMethod policy + Filament resource (protect Cash)"
```

---

### Task 8: ExpenseCategory — modelo, seeder, policy, resource

**Files:**
- Create: `app/Models/ExpenseCategory.php` + migración + factory
- Create: `database/seeders/ExpenseCategorySeeder.php`
- Create: `app/Policies/ExpenseCategoryPolicy.php`
- Create: `app/Filament/Resources/ExpenseCategories/*`
- Test: `tests/Feature/ExpenseCategoryTest.php`

- [ ] **Step 1: Migración**

```bash
php artisan make:migration create_expense_categories_table --no-interaction
```

```php
Schema::create('expense_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

- [ ] **Step 2: Modelo + factory**

```bash
php artisan make:model ExpenseCategory --factory --no-interaction
```

`app/Models/ExpenseCategory.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'is_active'])]
class ExpenseCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseCategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
```

Factory `definition()`:

```php
return ['name' => fake()->unique()->word(), 'is_active' => true];
```

- [ ] **Step 3: Test que falla**

Create `tests/Feature/ExpenseCategoryTest.php`:

```php
<?php

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('siembra las categorías de gasto base sin duplicar', function () {
    $this->seed(\Database\Seeders\ExpenseCategorySeeder::class);
    $this->seed(\Database\Seeders\ExpenseCategorySeeder::class);

    expect(ExpenseCategory::pluck('name')->all())
        ->toContain('Arriendo', 'Salario', 'Lentes terminados', 'Exámenes', 'Digitales', 'Otros')
        ->and(ExpenseCategory::where('name', 'Arriendo')->count())->toBe(1);
});
```

- [ ] **Step 4: Correr (debe fallar)**

Run: `php artisan test --compact --filter=ExpenseCategoryTest`
Expected: FAIL — seeder no existe.

- [ ] **Step 5: Seeder**

Create `database/seeders/ExpenseCategorySeeder.php`:

```php
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
```

- [ ] **Step 6: Migrar + correr (debe pasar)**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=ExpenseCategoryTest
```
Expected: PASS.

- [ ] **Step 7: Policy (solo admin)**

Create `app/Policies/ExpenseCategoryPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 8: Resource Filament + etiquetas**

```bash
php artisan make:filament-resource ExpenseCategory --generate --no-interaction
```
Añadir labels: `modelLabel` = 'Categoría de gasto', `pluralModelLabel` = 'Categorías de gasto'. Columna `name` → "Nombre", `is_active` → "Activa".

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add ExpenseCategory (seeded) with admin-only policy and resource"
```

---

### Task 9: Customer — modelo (soft deletes), policy, resource

**Files:**
- Create: `app/Models/Customer.php` + migración + factory
- Create: `app/Policies/CustomerPolicy.php`
- Create: `app/Filament/Resources/Customers/*`
- Test: `tests/Feature/CustomerTest.php`

- [ ] **Step 1: Migración**

```bash
php artisan make:migration create_customers_table --no-interaction
```

```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('id_number')->nullable()->unique();
    $table->string('phone')->nullable();
    $table->string('address')->nullable();
    $table->string('city')->nullable();
    $table->unsignedSmallInteger('age')->nullable();
    $table->string('email')->nullable();
    $table->text('notes')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

- [ ] **Step 2: Modelo + factory**

```bash
php artisan make:model Customer --factory --no-interaction
```

`app/Models/Customer.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'id_number', 'phone', 'address', 'city', 'age', 'email', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory, SoftDeletes;
}
```

Factory `definition()`:

```php
return [
    'name' => fake()->name(),
    'id_number' => fake()->unique()->numerify('##########'),
    'phone' => fake()->numerify('3#########'),
    'address' => fake()->streetAddress(),
    'city' => fake()->city(),
    'age' => fake()->numberBetween(8, 85),
    'email' => fake()->optional()->safeEmail(),
    'notes' => null,
];
```

- [ ] **Step 3: Test que falla (policy de borrado)**

Create `tests/Feature/CustomerTest.php`:

```php
<?php

use App\Models\Customer;
use App\Models\User;
use App\Policies\CustomerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el vendedor crea/edita pero no elimina clientes', function () {
    $policy = new CustomerPolicy;
    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();

    expect($policy->create($seller))->toBeTrue()
        ->and($policy->update($seller, $customer))->toBeTrue()
        ->and($policy->delete($seller, $customer))->toBeFalse();
});

it('el admin sí elimina clientes', function () {
    $policy = new CustomerPolicy;
    expect($policy->delete(User::factory()->admin()->create(), Customer::factory()->create()))->toBeTrue();
});
```

- [ ] **Step 4: Correr (debe fallar)**

Run: `php artisan test --compact --filter=CustomerTest`
Expected: FAIL — `CustomerPolicy` no existe.

- [ ] **Step 5: Policy con el trait base**

Create `app/Policies/CustomerPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Policies\Concerns\AdminManaged;

class CustomerPolicy
{
    use AdminManaged;
}
```

> El trait `AdminManaged` ya define `viewAny/view/create/update` = true y `delete/restore/forceDelete` = solo admin. Los métodos del trait reciben solo `User` (Laravel ignora el 2º argumento si no se declara), lo que satisface las llamadas del test.

- [ ] **Step 6: Migrar + correr (debe pasar)**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=CustomerTest
```
Expected: PASS.

- [ ] **Step 7: Resource Filament + etiquetas**

```bash
php artisan make:filament-resource Customer --generate --no-interaction
```
Labels: `modelLabel` = 'Cliente', `pluralModelLabel` = 'Clientes'. Columnas: `name` → "Nombre", `id_number` → "Cédula", `phone` → "Celular", `city` → "Ciudad", `email` → "Correo". Habilitar búsqueda por `name`, `id_number`, `phone`.

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add Customer with soft deletes, policy and resource"
```

---

### Task 10: Product — enum de categoría, modelo, policy, resource (costo oculto al vendedor)

**Files:**
- Create: `app/Enums/ProductCategory.php`
- Create: `app/Models/Product.php` + migración + factory
- Create: `app/Policies/ProductPolicy.php`
- Create: `app/Filament/Resources/Products/*`
- Test: `tests/Feature/ProductTest.php`

- [ ] **Step 1: Enum de categoría**

Create `app/Enums/ProductCategory.php`:

```php
<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Lens = 'lens';
    case Frame = 'frame';
    case Filter = 'filter';
    case Accessory = 'accessory';
    case Promo = 'promo';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Lens => 'Lente',
            self::Frame => 'Montura',
            self::Filter => 'Filtro',
            self::Accessory => 'Accesorio',
            self::Promo => 'Promoción',
            self::Service => 'Servicio',
        };
    }

    /** @return array<string,string> value => label, para selects de Filament. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
```

- [ ] **Step 2: Migración**

```bash
php artisan make:migration create_products_table --no-interaction
```

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('sku')->nullable();
    $table->string('category');
    $table->string('brand')->nullable();
    $table->unsignedBigInteger('price')->default(0);
    $table->unsignedBigInteger('cost')->default(0);
    $table->boolean('is_stockable')->default(true);
    $table->integer('stock')->nullable();
    $table->boolean('is_active')->default(true);
    $table->json('specs')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

> Nota: la columna se llama `specs` (no `attributes`) a propósito: `attributes` colisiona con la propiedad interna `$attributes` de Eloquent y produce lecturas inconsistentes.

- [ ] **Step 3: Modelo + factory**

```bash
php artisan make:model Product --factory --no-interaction
```

`app/Models/Product.php`:

```php
<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sku', 'category', 'brand', 'price', 'cost', 'is_stockable', 'stock', 'is_active', 'specs'])]
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'price' => 'integer',
            'cost' => 'integer',
            'is_stockable' => 'boolean',
            'is_active' => 'boolean',
            'specs' => 'array',
        ];
    }

    /** Margen en pesos (precio − costo). */
    public function margin(): int
    {
        return $this->price - $this->cost;
    }
}
```

Factory `definition()`:

```php
return [
    'name' => fake()->words(2, true),
    'sku' => fake()->optional()->bothify('REF-####'),
    'category' => fake()->randomElement(\App\Enums\ProductCategory::cases())->value,
    'brand' => fake()->optional()->company(),
    'price' => fake()->numberBetween(20_000, 600_000),
    'cost' => fake()->numberBetween(5_000, 200_000),
    'is_stockable' => true,
    'stock' => fake()->numberBetween(0, 50),
    'is_active' => true,
    'specs' => null,
];
```

- [ ] **Step 4: Test que falla**

Create `tests/Feature/ProductTest.php`:

```php
<?php

use App\Enums\ProductCategory;
use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calcula el margen', function () {
    $product = Product::factory()->create(['price' => 100_000, 'cost' => 60_000]);
    expect($product->margin())->toBe(40_000);
});

it('castea la categoría a enum', function () {
    $product = Product::factory()->create(['category' => ProductCategory::Frame->value]);
    expect($product->category)->toBe(ProductCategory::Frame);
});

it('el vendedor ve productos pero no los elimina', function () {
    $policy = new ProductPolicy;
    $seller = User::factory()->seller()->create();
    expect($policy->viewAny($seller))->toBeTrue()
        ->and($policy->create($seller))->toBeFalse()
        ->and($policy->delete($seller, Product::factory()->create()))->toBeFalse();
});

it('el admin gestiona productos', function () {
    $policy = new ProductPolicy;
    $admin = User::factory()->admin()->create();
    expect($policy->create($admin))->toBeTrue()
        ->and($policy->delete($admin, Product::factory()->create()))->toBeTrue();
});
```

- [ ] **Step 5: Correr (debe fallar)**

Run: `php artisan test --compact --filter=ProductTest`
Expected: FAIL — `ProductPolicy` no existe.

- [ ] **Step 6: Policy (vendedor: solo lectura; admin: todo)**

Create `app/Policies/ProductPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 7: Migrar + correr (debe pasar)**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=ProductTest
```
Expected: PASS.

- [ ] **Step 8: Resource Filament + ocultar costo/margen al vendedor**

```bash
php artisan make:filament-resource Product --generate --no-interaction
```

Ajustes en `ProductResource`:
- Labels: `modelLabel` = 'Producto', `pluralModelLabel` = 'Productos'.
- Campo `category`: `Select::make('category')->options(\App\Enums\ProductCategory::options())`.
- Ocultar **costo y margen** a vendedores. En el form, el campo `cost`:
  ```php
  TextInput::make('cost')->numeric()
      ->visible(fn () => auth()->user()?->isAdmin() === true);
  ```
- En la tabla, columna `cost` (y cualquier columna de margen):
  ```php
  TextColumn::make('cost')->money('COP')
      ->visible(fn () => auth()->user()?->isAdmin() === true);
  ```
- Columnas: `name` → "Nombre", `category` → "Categoría", `price` → "Precio", `stock` → "Stock".

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add Product catalog with cost hidden from sellers"
```

---

### Task 11: Expense — modelo (soft deletes + auditoría), policy admin, resource

**Files:**
- Create: `app/Models/Expense.php` + migración + factory
- Create: `app/Policies/ExpensePolicy.php`
- Create: `app/Filament/Resources/Expenses/*`
- Test: `tests/Feature/ExpenseTest.php`

- [ ] **Step 1: Migración**

```bash
php artisan make:migration create_expenses_table --no-interaction
```

```php
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('expense_category_id')->constrained();
    $table->string('description');
    $table->unsignedBigInteger('amount');
    $table->foreignId('payment_method_id')->nullable()->constrained();
    $table->date('spent_at');
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->text('notes')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

- [ ] **Step 2: Modelo + factory (con auditoría)**

```bash
php artisan make:model Expense --factory --no-interaction
```

`app/Models/Expense.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['expense_category_id', 'description', 'amount', 'payment_method_id', 'spent_at', 'created_by', 'notes'])]
class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'spent_at' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['description', 'amount', 'expense_category_id', 'spent_at'])
            ->logOnlyDirty();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
```

Factory `definition()`:

```php
return [
    'expense_category_id' => \App\Models\ExpenseCategory::factory(),
    'description' => fake()->sentence(3),
    'amount' => fake()->numberBetween(10_000, 2_000_000),
    'payment_method_id' => null,
    'spent_at' => fake()->dateTimeThisMonth()->format('Y-m-d'),
    'created_by' => null,
    'notes' => null,
];
```

- [ ] **Step 3: Test que falla (auditoría + policy)**

Create `tests/Feature/ExpenseTest.php`:

```php
<?php

use App\Models\Expense;
use App\Models\User;
use App\Policies\ExpensePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registra actividad al crear un gasto', function () {
    $expense = Expense::factory()->create();

    expect(\Spatie\Activitylog\Models\Activity::where('subject_type', Expense::class)
        ->where('subject_id', $expense->id)->exists())->toBeTrue();
});

it('los gastos son exclusivos del admin', function () {
    $policy = new ExpensePolicy;
    $seller = User::factory()->seller()->create();
    $admin = User::factory()->admin()->create();

    expect($policy->viewAny($seller))->toBeFalse()
        ->and($policy->viewAny($admin))->toBeTrue()
        ->and($policy->delete($admin, Expense::factory()->create()))->toBeTrue();
});
```

- [ ] **Step 4: Correr (debe fallar)**

Run: `php artisan test --compact --filter=ExpenseTest`
Expected: FAIL — `ExpensePolicy` no existe.

- [ ] **Step 5: Policy (solo admin, todos los métodos)**

Create `app/Policies/ExpensePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 6: Migrar + correr (debe pasar)**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=ExpenseTest
```
Expected: PASS.

- [ ] **Step 7: Resource Filament + etiquetas**

```bash
php artisan make:filament-resource Expense --generate --no-interaction
```
Labels: `modelLabel` = 'Gasto', `pluralModelLabel` = 'Gastos'. Campo `expense_category_id` como relación `->relationship('category', 'name')`; `payment_method_id` como `->relationship('paymentMethod', 'name')`. Al crear, fijar `created_by = auth()->id()` (en `mutateFormDataBeforeCreate` o vía `->default(auth()->id())` oculto). Columnas: `description` → "Descripción", `amount` → "Monto" (`->money('COP')`), `spent_at` → "Fecha".

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add Expense with activity log, admin-only policy and resource"
```

---

### Task 12: Prescription — enum tipo de lente, modelo (soft deletes), policy, resource

**Files:**
- Create: `app/Enums/LensType.php`
- Create: `app/Models/Prescription.php` + migración + factory
- Create: `app/Policies/PrescriptionPolicy.php`
- Create: `app/Filament/Resources/Prescriptions/*`
- Test: `tests/Feature/PrescriptionTest.php`

- [ ] **Step 1: Enum tipo de lente**

Create `app/Enums/LensType.php`:

```php
<?php

namespace App\Enums;

enum LensType: string
{
    case SingleVision = 'single_vision';
    case ExtendedRange = 'extended_range';
    case Bifocal = 'bifocal';
    case Progressive = 'progressive';

    public function label(): string
    {
        return match ($this) {
            self::SingleVision => 'Monofocal',
            self::ExtendedRange => 'Monofocal rango extendido',
            self::Bifocal => 'Bifocal',
            self::Progressive => 'Progresivo',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
```

- [ ] **Step 2: Migración**

```bash
php artisan make:migration create_prescriptions_table --no-interaction
```

```php
Schema::create('prescriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('sale_id')->nullable(); // FK se añade en Plan 2 (sales aún no existe)
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->date('exam_date');
    // Ojo derecho (OD)
    $table->string('od_sphere')->nullable();
    $table->string('od_cylinder')->nullable();
    $table->string('od_axis')->nullable();
    $table->string('od_add')->nullable();
    $table->string('od_va')->nullable();
    $table->string('od_pd')->nullable();
    // Ojo izquierdo (OS)
    $table->string('os_sphere')->nullable();
    $table->string('os_cylinder')->nullable();
    $table->string('os_axis')->nullable();
    $table->string('os_add')->nullable();
    $table->string('os_va')->nullable();
    $table->string('os_pd')->nullable();
    $table->string('lens_type')->nullable();
    $table->json('filters')->nullable();
    $table->string('usage')->nullable();
    $table->string('control_period')->nullable();
    $table->text('diagnosis')->nullable();
    $table->string('drops')->nullable();
    $table->string('lensometry')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

> Nota: `sale_id` queda como columna simple sin FK porque la tabla `sales` se crea en el Plan 2; allí se añadirá la constraint.

- [ ] **Step 3: Modelo + factory**

```bash
php artisan make:model Prescription --factory --no-interaction
```

`app/Models/Prescription.php`:

```php
<?php

namespace App\Models;

use App\Enums\LensType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer_id', 'sale_id', 'created_by', 'exam_date',
    'od_sphere', 'od_cylinder', 'od_axis', 'od_add', 'od_va', 'od_pd',
    'os_sphere', 'os_cylinder', 'os_axis', 'os_add', 'os_va', 'os_pd',
    'lens_type', 'filters', 'usage', 'control_period', 'diagnosis', 'drops', 'lensometry',
])]
class Prescription extends Model
{
    /** @use HasFactory<\Database\Factories\PrescriptionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'filters' => 'array',
            'lens_type' => LensType::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
```

Factory `definition()`:

```php
return [
    'customer_id' => \App\Models\Customer::factory(),
    'created_by' => null,
    'exam_date' => fake()->dateTimeThisYear()->format('Y-m-d'),
    'od_sphere' => '-0.25',
    'od_cylinder' => '-2.00',
    'od_axis' => '0',
    'os_sphere' => 'N',
    'os_cylinder' => '-2.75',
    'os_axis' => '0',
    'lens_type' => \App\Enums\LensType::ExtendedRange->value,
    'filters' => ['Fotocromático', 'Antirreflejo Blue'],
    'usage' => 'Prolongado',
    'control_period' => 'Anual',
    'diagnosis' => 'Paciente refiere mala visión en VL y VP',
    'drops' => null,
    'lensometry' => null,
];
```

- [ ] **Step 4: Test que falla**

Create `tests/Feature/PrescriptionTest.php`:

```php
<?php

use App\Enums\LensType;
use App\Models\Prescription;
use App\Models\User;
use App\Policies\PrescriptionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('guarda filtros como array y el tipo de lente como enum', function () {
    $rx = Prescription::factory()->create();

    expect($rx->filters)->toBeArray()
        ->and($rx->filters)->toContain('Antirreflejo Blue')
        ->and($rx->lens_type)->toBe(LensType::ExtendedRange);
});

it('el vendedor crea/edita prescripciones pero no las elimina', function () {
    $policy = new PrescriptionPolicy;
    $seller = User::factory()->seller()->create();

    expect($policy->create($seller))->toBeTrue()
        ->and($policy->delete($seller, Prescription::factory()->create()))->toBeFalse();
});
```

- [ ] **Step 5: Correr (debe fallar)**

Run: `php artisan test --compact --filter=PrescriptionTest`
Expected: FAIL — `PrescriptionPolicy` no existe.

- [ ] **Step 6: Policy con el trait base**

Create `app/Policies/PrescriptionPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Policies\Concerns\AdminManaged;

class PrescriptionPolicy
{
    use AdminManaged;
}
```

- [ ] **Step 7: Migrar + correr (debe pasar)**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=PrescriptionTest
```
Expected: PASS.

- [ ] **Step 8: Resource Filament + etiquetas**

```bash
php artisan make:filament-resource Prescription --generate --no-interaction
```
Ajustes:
- Labels: `modelLabel` = 'Prescripción', `pluralModelLabel` = 'Prescripciones'.
- `customer_id` → `->relationship('customer', 'name')` (label "Cliente").
- `lens_type` → `Select::make('lens_type')->options(\App\Enums\LensType::options())` (label "Tipo de lente").
- `filters` → `Select::make('filters')->multiple()->options(['Fotocromático'=>'Fotocromático','Antirreflejo Blue'=>'Antirreflejo Blue','FotoBlue'=>'FotoBlue'])` (label "Filtros").
- Agrupar campos OD/OS en `Section` ("Ojo derecho (OD)" / "Ojo izquierdo (OS)") con columnas: esfera, cilindro, eje, add, AV, DP.
- Fijar `created_by = auth()->id()` al crear.

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add Prescription module with structured Rx fields"
```

---

### Task 13: Registrar seeders y verificación final

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: toda la suite

- [ ] **Step 1: Encadenar seeders base**

Modify `database/seeders/DatabaseSeeder.php` — en `run()`:

```php
$this->call([
    BusinessSettingSeeder::class,
    PaymentMethodSeeder::class,
    ExpenseCategorySeeder::class,
]);

// Usuario admin de arranque (solo si no existe)
\App\Models\User::firstOrCreate(
    ['email' => 'admin@optica.test'],
    ['name' => 'Administrador', 'password' => bcrypt('password'), 'role' => \App\Enums\UserRole::Admin, 'is_active' => true],
);
```

- [ ] **Step 2: Migración fresca + seed**

```bash
php artisan migrate:fresh --seed --no-interaction
```
Expected: corre sin errores; crea Efectivo, categorías de gasto, settings y usuario admin.

- [ ] **Step 3: Correr toda la suite**

Run: `php artisan test --compact`
Expected: PASS (todos los tests de Fase 0 + Fase 1).

- [ ] **Step 4: Verificar acceso al panel manualmente (opcional)**

```bash
php artisan serve
```
Entrar a `/admin`, login con `admin@optica.test` / `password`. Verificar que aparecen los recursos: Clientes, Productos, Prescripciones, Métodos de pago, Categorías de gasto, Gastos. Crear un vendedor y confirmar que NO ve Gastos/Métodos de pago/Categorías y que NO puede borrar.

- [ ] **Step 5: Commit final**

```bash
git add -A
git commit -m "chore: wire base seeders and bootstrap admin user"
```

---

## Self-Review (cubierto por este plan)

- **Roles + acceso panel** → Task 3. **Anti-fraude (no borrar)** → Task 4 (trait), aplicado en Tasks 9/12; admin-only en 7/8/11.
- **Métodos de pago con Efectivo protegido** → Tasks 6–7.
- **Categorías de gasto sembradas** → Task 8. **Gastos + auditoría** → Task 11.
- **Clientes** → Task 9. **Catálogo con costo/stock, costo oculto al vendedor** → Task 10. **Prescripción estructurada** → Task 12.
- **BusinessSetting** → Task 5. **Auditoría (activitylog)** → Task 2 + Task 11.
- **Fuera de este plan (Fase 2–4):** sales, sale_items, payments, cash_closes, reportes y POS Vue → planes siguientes. La columna `prescriptions.sale_id` queda lista para su FK en el Plan 2.
