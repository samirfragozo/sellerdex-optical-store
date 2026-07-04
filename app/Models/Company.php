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
