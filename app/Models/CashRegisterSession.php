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
}
