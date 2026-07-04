# Diseño — Arquitectura Multi-Company (SaaS)

Fecha: 2026-07-03
Estado: aprobado para plan de implementación

## Objetivo

Convertir Sellerdex en una plataforma SaaS multi-tenant donde múltiples ópticas independientes comparten una sola instalación. Cada empresa ve únicamente sus propios datos. El dueño de la plataforma gestiona todas las empresas desde un panel de superadmin.

Principio rector: **aislamiento por convención con un único punto de control** — un trait + un Global Scope en cada modelo garantizan el filtrado; el superadmin bypasea el scope de forma explícita.

## Enfoque elegido: Single DB + `company_id` + Global Scope

Una sola base de datos PostgreSQL. Todas las tablas de negocio tienen `company_id`. Un trait `BelongsToCompany` aplica el scope y rellena el campo automáticamente. Sin dependencias nuevas.

## Modelo de datos

### Tabla `companies`

| Columna | Tipo | Notas |
|---------|------|-------|
| id | bigint PK | |
| name | varchar | Nombre comercial de la óptica |
| tax_id | varchar nullable | NIT / RUT |
| address | varchar nullable | |
| phones | varchar nullable | |
| logo | varchar nullable | Ruta del logo |
| slug | varchar unique | Identificador URL-friendly |
| is_active | boolean default true | false = cuenta suspendida |
| plan | varchar default 'free' | Preparado para planes futuros |
| timestamps | | |

### Cambios a `users`

- Agregar `company_id` nullable FK → `companies.id`
- `company_id = null` identifica al superadmin
- Un usuario pertenece a exactamente una empresa (o es superadmin)

### Modelos que reciben `company_id`

Todos los modelos de negocio: `Customer`, `Product`, `ProductCategory`, `PaymentMethod`, `ExpenseCategory`, `Expense`, `Sale`, `SaleItem`, `Payment`, `Prescription`, `LensOrder`, `Supplier`, `PurchaseOrder`, `PurchaseOrderItem`, `CashClose`.

`BusinessSetting` se reemplaza por `Company` — sus campos (`name`, `tax_id`, `address`, `phones`, `logo`) migran a `companies`.

## Mecanismo de tenancy

### Trait `BelongsToCompany`

```php
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::creating(fn ($model) =>
            $model->company_id ??= Auth::user()?->company_id
        );
    }
}
```

Cada modelo de negocio usa este trait en lugar de lógica propia.

### `CompanyScope`

```php
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

### Bypass explícito

Para queries globales (métricas del superadmin): `Model::withoutGlobalScope(CompanyScope::class)`.

## Roles

Los roles de Spatie (`admin`, `seller`) se mantienen sin cambios. Viven dentro del contexto de una empresa: el admin de Óptica A no puede ver ni gestionar usuarios de Óptica B. El superadmin tiene rol `superadmin` y `company_id = null`.

## Paneles Filament

### `/admin` — Panel de empresa (existente)

Sin cambios estructurales. El Global Scope garantiza que cada empresa ve solo sus datos. `BusinessSetting` en el panel pasa a gestionar los campos de su propio `Company`.

### `/superadmin` — Panel de plataforma (nuevo)

Accesible solo para usuarios con rol `superadmin` (`company_id = null`). Recursos:

- **Companies** — CRUD completo: crear, editar, activar/suspender (`is_active`)
- **Users** — vista global de todos los usuarios (filtrable por empresa)
- **Métricas globales** — total de empresas activas, ventas del mes por empresa

## Registro y onboarding

URL pública: `/register`

Formulario en una sola pantalla (Inertia):
1. Nombre de la empresa
2. Nombre, email y contraseña del primer administrador

Al enviar:
1. Se crea `Company` con `is_active = true`
2. Se crea `User` con `company_id` de la empresa recién creada y rol `admin`
3. Se autentica automáticamente y redirige a `/admin`

Todo en una transacción DB. Si falla cualquier paso, no queda empresa huérfana ni usuario sin empresa.

## Empresas suspendidas

Middleware `EnsureCompanyIsActive`:
- Se aplica a todas las rutas de `/admin`
- Si `auth()->user()->company?->is_active === false` → redirige a pantalla de cuenta suspendida
- El superadmin no pasa por este middleware

## Migración de datos existentes

Los datos actuales en desarrollo no tienen empresa. La migración crea una empresa por defecto (`id = 1`) y asigna `company_id = 1` a todos los registros existentes mediante `DB::table(...)->update(['company_id' => 1])` en la misma migración.

## Cambio de base de datos

De SQLite (desarrollo) a PostgreSQL (producción en Railway). El `.env` de producción apunta a `DB_CONNECTION=pgsql`. Los tests siguen corriendo en SQLite salvo que se configure lo contrario.

## Fuera de alcance (YAGNI)

- Planes de suscripción con cobro automático (Stripe/etc.)
- Límites por plan (max usuarios, max ventas) — preparado con `plan` en `Company` pero sin lógica
- Impersonación de admin desde superadmin
- Subdominio por empresa
- Catálogo de lentes compartido entre empresas

## Archivos afectados (estimado)

**Nuevos:**
- `app/Models/Company.php`
- `app/Traits/BelongsToCompany.php`
- `app/Scopes/CompanyScope.php`
- `app/Http/Middleware/EnsureCompanyIsActive.php`
- `app/Filament/Superadmin/` — panel y recursos
- `app/Http/Controllers/RegisterCompanyController.php`
- `resources/js/pages/RegisterCompany.vue`
- `database/migrations/*_create_companies_table.php`
- `database/migrations/*_add_company_id_to_*`

**Modificados:**
- `app/Models/User.php` — relación `company()`
- Todos los modelos de negocio — trait `BelongsToCompany`
- `app/Providers/AppServiceProvider.php` — registro del middleware
- `bootstrap/app.php` — middleware
- `routes/web.php` — ruta `/register`
- `database/seeders/` — company por defecto
