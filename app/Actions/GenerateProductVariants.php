<?php

namespace App\Actions;

use App\Models\Option;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GenerateProductVariants
{
    /**
     * Create one child Product per combination of options across the base
     * product's required, single-select option groups. Skips combinations
     * that already exist as a variant.
     *
     * @return array{created: int, skipped: int}
     */
    public function handle(Product $base): array
    {
        $groups = $base->optionGroups()
            ->where('option_groups.is_required', true)
            ->with('options')
            ->get();

        if ($groups->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        $optionLists = $groups
            ->map(fn ($group) => $group->options->where('is_active', true)->values())
            ->values();

        if ($optionLists->contains(fn ($list) => $list->isEmpty())) {
            return ['created' => 0, 'skipped' => 0];
        }

        $existingSets = $base->variants()
            ->with('variantOptions')
            ->get()
            ->map(fn (Product $variant) => $variant->variantOptions->pluck('id')->sort()->values()->all())
            ->all();

        $created = 0;
        $skipped = 0;

        foreach ($this->cartesian($optionLists->all()) as $combo) {
            /** @var array<int, Option> $combo */
            $optionIds = collect($combo)->pluck('id')->sort()->values()->all();

            if (in_array($optionIds, $existingSets, true)) {
                $skipped++;

                continue;
            }

            $name = collect($combo)->reduce(
                fn (string $carry, Option $option) => "{$carry} {$option->name}",
                $base->name,
            );
            $slug = collect($combo)->map(fn (Option $option) => Str::slug($option->name))->implode('-');

            $variant = Product::create([
                'name' => $name,
                'sku' => strtoupper("{$base->sku}-{$slug}"),
                'product_category_id' => $base->product_category_id,
                'base_product_id' => $base->id,
                'price' => 0,
                'cost' => 0,
                'is_stockable' => true,
                'stock' => 0,
                'is_active' => true,
                'is_pos_selectable' => false,
            ]);

            $variant->variantOptions()->attach($optionIds);

            $existingSets[] = $optionIds;
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, Collection<int, Option>>  $lists
     * @return array<int, array<int, Option>>
     */
    private function cartesian(array $lists): array
    {
        $result = [[]];

        foreach ($lists as $list) {
            $next = [];

            foreach ($result as $partial) {
                foreach ($list as $option) {
                    $next[] = [...$partial, $option];
                }
            }

            $result = $next;
        }

        return $result;
    }
}
