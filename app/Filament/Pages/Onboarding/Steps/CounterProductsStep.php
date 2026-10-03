<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ReferenceCounterCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Everything the shop sells besides lenses: categories with their base products. */
class CounterProductsStep extends OnboardingStep
{
    private const OWN_RESOURCE_KEYS = ['frame', 'sunglasses'];

    public static function key(): string
    {
        return 'counter_products';
    }

    public function label(): string
    {
        return __('app.onboarding.counter_products.label');
    }

    public function description(): string
    {
        return __('app.onboarding.counter_products.description');
    }

    public function components(): array
    {
        return [
            Repeater::make('categories')
                ->hiddenLabel()
                ->schema([
                    Hidden::make('category_id'),
                    Hidden::make('key'),
                    Hidden::make('is_locked'),
                    TextInput::make('name')->label(__('app.fields.name'))->required()->distinct()->maxLength(255)
                        ->rule(fn (Get $get) => Rule::unique('product_categories', 'name')
                            ->where('company_id', auth()->user()->company_id)
                            ->ignore($get('category_id'))),
                    Repeater::make('products')
                        ->hiddenLabel()
                        ->schema([
                            Hidden::make('product_id'),
                            Hidden::make('is_locked'),
                            TextInput::make('name')->label(__('app.fields.name'))->required()->distinct()->maxLength(255),
                            TextInput::make('price')->label(__('app.fields.price'))->required()->integer()->minValue(0)->prefix('$'),
                            TextInput::make('cost')->label(__('app.fields.cost'))->required()->integer()->minValue(0)->prefix('$'),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->deleteAction(fn (Action $action) => $this->hideForLockedRows($action))
                        ->addActionLabel(__('app.onboarding.counter_products.add_product')),
                ])
                ->defaultItems(0)
                ->deleteAction(fn (Action $action) => $this->hideForLockedRows($action))
                ->addActionLabel(__('app.onboarding.counter_products.add_category')),
            Radio::make('tracks_inventory')
                ->label(__('app.onboarding.counter_products.tracks_inventory'))
                ->helperText(__('app.onboarding.counter_products.tracks_inventory_help'))
                ->boolean()->required()->inline(),
        ];
    }

    public function fill(Company $company): array
    {
        $categories = $this->categories()->with(['products' => fn ($query) => $query->sellableBase()->orderBy('id')])->orderBy('id')->get();

        $tracksInventory = $company->tracks_inventory === null ? null : (int) $company->tracks_inventory;

        if ($this->products()->exists()) {
            return ['tracks_inventory' => $tracksInventory, 'categories' => $categories->map(fn (ProductCategory $category) => [
                'category_id' => $category->id,
                'key' => $category->key,
                'name' => $category->name,
                'is_locked' => ! $category->isDeletable(),
                'products' => $category->products->map(fn (Product $product) => [
                    'product_id' => $product->id, 'is_locked' => ! $product->isDeletable(), 'name' => $product->name, 'price' => $product->price, 'cost' => $product->cost,
                ])->all(),
            ])->all()];
        }

        $byKey = $categories->keyBy('key');
        $suggested = array_map(fn (array $reference) => [
            'category_id' => $byKey->get($reference['key'])?->id,
            'key' => $reference['key'],
            'name' => $byKey->get($reference['key'])->name ?? $reference['name'],
            'is_locked' => $byKey->has($reference['key']) && ! $byKey->get($reference['key'])->isDeletable(),
            'products' => array_map(fn (array $product) => ['product_id' => null, ...$product], $reference['products']),
        ], ReferenceCounterCatalog::categories());

        $others = $categories->whereNotIn('key', array_column(ReferenceCounterCatalog::categories(), 'key'))
            ->map(fn (ProductCategory $category) => [
                'category_id' => $category->id, 'key' => $category->key, 'name' => $category->name, 'is_locked' => ! $category->isDeletable(), 'products' => [],
            ]);

        return ['tracks_inventory' => $tracksInventory, 'categories' => [...$suggested, ...$others->values()->all()]];
    }

    public function save(Company $company, array $state): void
    {
        DB::transaction(function () use ($company, $state) {
            $keptCategoryIds = [];
            $keptProductIds = [];

            foreach ($state['categories'] ?? [] as $row) {
                $category = ($row['category_id'] ?? null) ? $this->categories()->find($row['category_id']) : null;
                $category ??= new ProductCategory([
                    'company_id' => $company->id,
                    'key' => $this->uniqueKey(($row['key'] ?? null) ?: Str::slug($row['name'])),
                    'is_active' => true,
                    'is_system' => false,
                    // ponytail: a generic sellable category carries the same VAT as accessories (IVA 19 %).
                    'default_tax_id' => ProductCategory::defaultTaxIdFor('accessory', $company->id),
                ]);
                $category->fill(['name' => $row['name']])->save();
                $keptCategoryIds[] = $category->id;

                foreach ($row['products'] ?? [] as $productRow) {
                    $product = ($productRow['product_id'] ?? null) ? $this->products()->find($productRow['product_id']) : null;
                    $product ??= new Product([
                        'company_id' => $company->id,
                        'is_active' => true,
                        'is_pos_selectable' => true,
                        'tax_id' => $category->default_tax_id,
                        'sku' => null,
                    ]);
                    $product->fill([
                        'name' => $productRow['name'],
                        'price' => (int) $productRow['price'],
                        'cost' => (int) $productRow['cost'],
                        'product_category_id' => $category->id,
                    ])->save();
                    $keptProductIds[] = $product->id;
                }
            }

            // The updating hook resets the count date when inventory is turned on.
            $company->update(['tracks_inventory' => (bool) $state['tracks_inventory']]);

            // The model guards keep products used by kit slots, system categories and those used by products or kit slots.
            // A guarded delete() returns false, which would stop `each->delete()`; keep going past it.
            $removed = [...$this->products()->whereNotIn('id', $keptProductIds)->get(), ...$this->categories()->whereNotIn('id', $keptCategoryIds)->get()];
            foreach ($removed as $model) {
                $model->delete();
            }
        });
    }

    public function isComplete(Company $company): bool
    {
        return $this->products()->exists() && $company->tracks_inventory !== null;
    }

    public function summary(Company $company): string
    {
        $products = $this->products()->get(['id', 'product_category_id']);

        return __('app.onboarding.counter_products.summary', [
            'products' => $products->count(),
            'categories' => $products->unique('product_category_id')->count(),
        ]);
    }

    /** Rows the model's deletion guard would keep cannot be removed here, so they never silently come back. */
    private function hideForLockedRows(Action $action): Action
    {
        return $action->visible(
            fn (array $arguments, Repeater $component): bool => ! ($component->getRawItemState($arguments['item'])['is_locked'] ?? false),
        );
    }

    /** @return Builder<ProductCategory> the company's counter categories, minus frames and sunglasses (their own resource, often hundreds of products) */
    private function categories(): Builder
    {
        return ProductCategory::query()->counter()->whereNotIn('key', self::OWN_RESOURCE_KEYS);
    }

    /** @return Builder<Product> the active base products of those categories */
    private function products(): Builder
    {
        return Product::query()->counter()->whereHas('category', fn (Builder $category) => $category->whereNotIn('key', self::OWN_RESOURCE_KEYS));
    }

    private function uniqueKey(string $base): string
    {
        $base = $base ?: 'category';
        $key = $base;
        for ($suffix = 2; ProductCategory::where('key', $key)->exists(); $suffix++) {
            $key = "{$base}-{$suffix}";
        }

        return $key;
    }
}
