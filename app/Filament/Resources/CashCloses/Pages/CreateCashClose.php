<?php

namespace App\Filament\Resources\CashCloses\Pages;

use App\Enums\CashCloseType;
use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\CashCloses\CashCloseResource;
use App\Services\CashCloseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateCashClose extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = CashCloseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $snapshot = app(CashCloseService::class)->compute(
            CashCloseType::from($data['type']),
            Carbon::parse($data['period_start'] ?? now()),
            (int) ($data['opening_cash'] ?? 0),
        );

        return array_merge($data, $snapshot);
    }
}
