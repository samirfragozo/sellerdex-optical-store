<?php

namespace App\Actions;

use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyClaimType;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WarrantyClaim;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class OpenWarrantyClaim
{
    /** Register a claim on a delivered line, inside its term (or the adaptation window for addition lenses). */
    public function handle(SaleItem $item, WarrantyClaimType $type, string $description, User $actor, ?CarbonInterface $receivedAt = null): WarrantyClaim
    {
        $receivedAt ??= today();
        $item->loadMissing(['sale', 'lensConfig.lensCombination.lensType']);

        $deadline = WarrantyClaim::deadlineFor($item, $type);
        $error = match (true) {
            $deadline === null => __('app.warranty.not_delivered'),
            $item->returnableQuantity() <= 0 => __('app.warranty.fully_returned'),
            $item->warrantyClaims()->where('status', '!=', WarrantyClaimStatus::Delivered->value)->exists() => __('app.warranty.already_open'),
            $type === WarrantyClaimType::Adaptation && $item->lensConfig?->lensCombination?->lensType?->kind?->requiresAddition() !== true => __('app.warranty.adaptation_not_addition'),
            $receivedAt->toDateString() < $item->sale->delivered_at->toDateString() => __('app.warranty.received_before_delivery', ['date' => $item->sale->delivered_at->format('d/m/Y')]),
            $receivedAt->gt($deadline) => __('app.warranty.out_of_term', ['date' => $deadline->format('d/m/Y')]),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['sale_item_id' => $error]);
        }

        return WarrantyClaim::create([
            'company_id' => $item->sale->company_id,
            'sale_item_id' => $item->id,
            'type' => $type,
            'customer_description' => $description,
            'received_at' => $receivedAt->toDateString(),
            'status' => WarrantyClaimStatus::Received,
            'user_id' => $actor->id,
        ]);
    }
}
