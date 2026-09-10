<?php

namespace App\Actions;

use App\Models\Option;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validate a set of chosen `Option` ids against the required option groups
 * attached to a product, and sum the price/cost deltas they carry.
 */
class ResolveProductOptions
{
    /**
     * @param  array<int,int>  $optionIds
     * @return array{price: int, cost: int, options: Collection<int, Option>}
     */
    public function handle(Product $product, array $optionIds): array
    {
        $groups = $product->optionGroups()->where('option_groups.is_active', true)->with('options')->get();

        $options = Option::query()
            ->with('group')
            ->whereIn('id', $optionIds)
            ->whereIn('option_group_id', $groups->pluck('id'))
            ->where('is_active', true)
            ->get();

        if ($options->count() !== count($optionIds)) {
            throw ValidationException::withMessages([
                'option_ids' => __('app.pos.lens_form.invalid_option'),
            ]);
        }

        foreach ($groups->where('is_required', true) as $group) {
            $selectedInGroup = $options->where('option_group_id', $group->id)->count();
            if ($selectedInGroup !== 1) {
                throw ValidationException::withMessages([
                    'option_ids' => __('app.pos.lens_form.option_group_required', ['group' => $group->name]),
                ]);
            }
        }

        return [
            'price' => (int) $options->sum('price'),
            'cost' => (int) $options->sum('cost'),
            'options' => $options,
        ];
    }
}
