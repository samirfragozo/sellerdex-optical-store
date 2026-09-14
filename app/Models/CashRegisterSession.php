<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\CashRegisterSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'opened_at', 'opening_cash', 'closed_at', 'closed_cash', 'expected_cash', 'difference', 'notes'])]
class CashRegisterSession extends Model
{
    /** @use HasFactory<CashRegisterSessionFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'opening_cash' => 'integer',
            'closed_at' => 'datetime',
            'closed_cash' => 'integer',
            'expected_cash' => 'integer',
            'difference' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the difference in sync once both sides of the count are known,
        // mirroring CashClose's own booted() hook.
        static::saving(function (CashRegisterSession $session): void {
            if ($session->closed_cash !== null && $session->expected_cash !== null) {
                $session->difference = $session->closed_cash - $session->expected_cash;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function openFor(?User $user): ?self
    {
        if ($user === null) {
            return null;
        }

        return static::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        // ponytail: implicit route binding must bypass global scopes so the controller's own
        // abort_if($cashRegisterSession->user_id !== $request->user()->id, 403) ownership check
        // can run and return 403, rather than CompanyScope filtering the record and returning 404 first.
        return $this->withoutGlobalScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }
}
