<?php

namespace App\Filament\Resources\WarrantyClaims\Pages;

use App\Actions\OpenWarrantyClaim;
use App\Enums\WarrantyClaimType;
use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\WarrantyClaims\WarrantyClaimResource;
use App\Models\SaleItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreateWarrantyClaim extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = WarrantyClaimResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(OpenWarrantyClaim::class)->handle(
                SaleItem::findOrFail($data['sale_item_id']),
                WarrantyClaimType::from($data['type']),
                $data['customer_description'],
                auth()->user(),
                Carbon::parse($data['received_at']),
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])->all());
        }
    }
}
