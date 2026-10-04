<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FollowUpReason: string implements HasLabel
{
    case PrescriptionExpiring = 'prescription_expiring';
    case BalanceDue = 'balance_due';
    case Birthday = 'birthday';

    public function label(): string
    {
        return __('app.follow_up.reasons.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
