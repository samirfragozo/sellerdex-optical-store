<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'cash_register_session_id', 'payment_method_id', 'expected', 'counted', 'difference'])]
class CashRegisterSessionCount extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'expected' => 'integer',
            'counted' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
