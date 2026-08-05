<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'company_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Role names. Roles themselves live in the database (spatie/laravel-permission);
     * these constants are the single source of truth for the role identifiers.
     */
    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SELLER = 'seller';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'superadmin' => $this->company_id === null && $this->hasRole(self::ROLE_SUPERADMIN),
            default => $this->company_id !== null && $this->is_active,
        };
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isSeller(): bool
    {
        return $this->hasRole(self::ROLE_SELLER);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'seller_id');
    }

    /**
     * Whether this user has any historical business records tied to it
     * (sales, payments, cash closes, expenses, prescriptions, purchase orders).
     * Used to guard hard deletes: only a user with none of these can be deleted.
     */
    public function hasBusinessActivity(): bool
    {
        return $this->sales()->exists()
            || Sale::where('created_by', $this->id)->exists()
            || Payment::where('received_by', $this->id)->exists()
            || CashClose::where('closed_by', $this->id)->exists()
            || Expense::where('created_by', $this->id)->exists()
            || Prescription::where('created_by', $this->id)->exists()
            || PurchaseOrder::where('created_by', $this->id)->exists();
    }
}
