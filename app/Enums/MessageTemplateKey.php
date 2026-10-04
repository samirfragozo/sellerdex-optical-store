<?php

namespace App\Enums;

enum MessageTemplateKey: string
{
    case OrderReady = 'order_ready';
    case PrescriptionExpiring = 'prescription_expiring';
    case BalanceDue = 'balance_due';
    case Birthday = 'birthday';

    public function label(): string
    {
        return __('app.message_templates.keys.'.$this->value);
    }

    /** Rendered in the customer locale (app.customer_locale, not the per-request one): messages go to customers, not in the staff member's interface language. */
    public function defaultBody(): string
    {
        return __('app.message_templates.defaults.'.$this->value, [], config('app.customer_locale'));
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
