<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WarrantyClaimStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case InReview = 'in_review';
    case AtSupplier = 'at_supplier';
    case Resolved = 'resolved';
    case Delivered = 'delivered';

    public function label(): string
    {
        return __('app.warranty_claim_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'gray',
            self::InReview => 'warning',
            self::AtSupplier => 'info',
            self::Resolved => 'success',
            self::Delivered => 'primary',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
