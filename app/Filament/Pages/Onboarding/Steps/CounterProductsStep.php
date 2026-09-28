<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ReferenceCounterCatalog;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Everything the shop sells besides lenses: categories with their base products. */
class CounterProductsStep extends OnboardingStep
{
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
                    TextInput::make('name')->label(__('app.fields.name'))->required()->distinct()->maxLength(255),
                    Repeater::make('products')
                        ->hiddenLabel()
                        ->schema([
                            Hidden::make('product_id'),
                            TextInput::make('name')->label(__('app.fields.name'))->required()->distinct()->maxLength(255),
                            TextInput::make('price')->label(__('app.fields.price'))->required()->integer()->minValue(0)->prefix('$'),
                            TextInput::make('cost')->label(__('app.fields.cost'))->required()->integer()->minValue(0)->prefix('$'),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel(__('app.onboarding.counter_products.add_product')),
                ])
                ->defaultItems(0)
                ->addActionLabel(__('app.onboarding.counter_products.add_category')),
        ];
    }

    public function fill(Company $company): array
    {
        $categories = $this->categories()->with(['products' => fn ($query) => $query->where('is_active', true)->whereNull('base_product_id')->orderBy('id')])->orderBy('id')->get();

        if ($this->products()->exists()) {
            return ['categories' => $categories->map(fn (ProductCategory $category) => [
                'category_id' => $category->id,
                'key' => $category->key,
                'name' => $category->name,
                'products' => $category->products->map(fn (Product $product) => [
                    'product_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'cost' => $product->cost,
                ])->all(),
            ])->all()];
        }

        $byKey = $categories->keyBy('key');
        $suggested = array_map(fn (array $reference) => [
            'category_id' => $byKey->get($reference['key'])?->id,
            'key' => $reference['key'],
            'name' => $byKey->get($reference['key'])->name ?? $reference['name'],
            'products' => array_map(fn (array $product) => ['product_id' => null, ...$product], $reference['products']),
        ], ReferenceCounterCatalog::categories());

        $others = $categories->whereNotIn('key', array_column(ReferenceCounterCatalog::categories(), 'key'))
            ->map(fn (ProductCategory $category) => ['category_id' => $category->id, 'key' => $category->key, 'name' => $category->name, 'products' => []]);

        return ['categories' => [...$suggested, ...$others->values()->all()]];
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

            $this->products()->whereNotIn('id', $keptProductIds)->delete();
            // The model guard keeps system categories and those used by products or kit slots.
            $this->categories()->whereNotIn('id', $keptCategoryIds)->get()->each->delete();
        });
    }

    public function isComplete(Company $company): bool
    {
        return $this->products()->exists();
    }

    public function summary(Company $company): string
    {
        $products = $this->products()->get(['id', 'product_category_id']);

        return __('app.onboarding.counter_products.summary', [
            'products' => $products->count(),
            'categories' => $products->unique('product_category_id')->count(),
        ]);
    }

    /** @return Builder<ProductCategory> the company's non-lens categories */
    private function categories(): Builder
    {
        return ProductCategory::query()->where('key', '!=', 'lens');
    }

    /** @return Builder<Product> the active base products of the non-lens categories */
    private function products(): Builder
    {
        return Product::query()->where('is_active', true)->whereNull('base_product_id')->whereHas('category', fn (Builder $query) => $query->where('key', '!=', 'lens'));
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
