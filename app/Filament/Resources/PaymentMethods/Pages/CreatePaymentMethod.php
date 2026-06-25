<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = PaymentMethodResource::class;
}
