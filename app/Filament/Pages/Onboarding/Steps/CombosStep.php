<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Enums\ArmadoFramePriceMode;
use App\Enums\KitPriceMode;
use App\Enums\KitTrigger;
use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ReferenceKit;
use Closure;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/** What comes with each sale: one kit slot per category, per combo (armado, frame alone, every sale). */
class CombosStep extends OnboardingStep
{
    public static function key(): string
    {
        return 'combos';
    }

    public function label(): string
    {
        return __('app.onboarding.combos.label');
    }

    public function description(): string
    {
        return __('app.onboarding.combos.description');
    }

    public function components(): array
    {
        $frame = ProductCategory::keyed('frame');

        return [
            Section::make(__('app.onboarding.combos.armado'))->schema([
                $this->slotsRepeater('armadoSlots', KitTrigger::Armado),
                TextEntry::make('armado_preview')
                    ->label(__('app.onboarding.combos.preview'))
                    ->html()
                    ->state(fn (Get $get): string => $this->preview($get('armadoSlots') ?? [])),
                Fieldset::make(__('app.onboarding.combos.frame_pricing'))->schema([
                    Radio::make('armado_frame_price_mode')
                        ->hiddenLabel()
                        ->options(ArmadoFramePriceMode::options())
                        ->required()
                        ->live(),
                    TextInput::make('armado_frame_discount_percent')
                        ->label(__('app.fields.discount_percent'))
                        ->visible(fn (Get $get): bool => $get('armado_frame_price_mode') === ArmadoFramePriceMode::DiscountPercent->value)
                        ->required()->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                ]),
            ]),
            ...($frame === null ? [] : [
                Section::make(__('app.onboarding.combos.frame'))->schema([
                    $this->slotsRepeater('frameSlots', KitTrigger::Category, $frame->id),
                ]),
            ]),
            Section::make(__('app.onboarding.combos.sale'))->schema([
                $this->slotsRepeater('saleSlots', KitTrigger::Sale),
            ]),
        ];
    }

    /** The per-product combos, only offered in the settings page. */
    public function productSection(): Section
    {
        return Section::make(__('app.onboarding.combos.product'))->schema([
            $this->slotsRepeater('productSlots', KitTrigger::Product),
        ]);
    }

    /** The repeaters load and save their slots through `Company::kitSlots()`; only the frame pricing is plain state. */
    public function fill(Company $company): array
    {
        if (! $company->kitSlots()->exists()) {
            ReferenceKit::installFor($company);
        }

        return Arr::only($company->attributesToArray(), ['armado_frame_price_mode', 'armado_frame_discount_percent']);
    }

    public function save(Company $company, array $state): void
    {
        $company->update([
            'armado_frame_price_mode' => $state['armado_frame_price_mode'],
            'armado_frame_discount_percent' => $state['armado_frame_discount_percent'] ?? 0,
        ]);
    }

    public function isComplete(Company $company): bool
    {
        return $this->reachedOffset($company) > 0 || $company->kitSlots()->exists();
    }

    public function summary(Company $company): string
    {
        return __('app.onboarding.combos.summary', [
            'count' => $company->kitSlots()->where('trigger', KitTrigger::Armado)->where('is_active', true)->count(),
        ]);
    }

    private function slotsRepeater(string $name, KitTrigger $trigger, ?int $triggerCategoryId = null): Repeater
    {
        $isProductCombo = $trigger === KitTrigger::Product;
        $priceModes = $trigger === KitTrigger::Armado ? KitPriceMode::options() : Arr::except(KitPriceMode::options(), KitPriceMode::AddedToLens->value);

        return Repeater::make($name)
            ->hiddenLabel()
            ->relationship('kitSlots', fn (Builder $query) => $query->where('trigger', $trigger)
                ->when($triggerCategoryId, fn (Builder $query) => $query->where('trigger_category_id', $triggerCategoryId)))
            // The decimal cast loads "20000.00", which the integer rule would reject; show it as typed ("20000", "12.5").
            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => [...$data, 'price_value' => (string) ((float) $data['price_value'])])
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'trigger' => $trigger, 'trigger_category_id' => $triggerCategoryId])
            ->orderColumn('sort_order')
            ->schema([
                ...($isProductCombo ? [
                    Select::make('trigger_product_id')->label(__('app.onboarding.combos.fields.trigger_product'))
                        ->options(fn () => Product::query()->counter()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required(),
                ] : []),
                Select::make('slot_category_id')->label(__('app.fields.category'))
                    ->options(fn () => ProductCategory::query()->counter()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    // A duplicate category in one combo would hit the unique index; refuse it as a validation error.
                    ->distinct(! $isProductCombo)
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('default_product_id', null);
                        $set('upgrade_product_id', null);
                    }),
                Select::make('default_product_id')->label(__('app.fields.product'))
                    ->options(fn (Get $get) => Product::query()->counter()->where('product_category_id', $get('slot_category_id'))->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->live(),
                Select::make('price_mode')->label(__('app.onboarding.combos.fields.price_mode'))
                    ->options($priceModes)
                    ->default(KitPriceMode::Free->value)
                    ->required()
                    ->live(),
                TextInput::make('price_value')
                    ->label(fn (Get $get): string => $get('price_mode') === KitPriceMode::DiscountPercent->value ? __('app.fields.discount_percent') : __('app.fields.amount'))
                    ->visible(fn (Get $get): bool => in_array($get('price_mode'), [KitPriceMode::DiscountPercent->value, KitPriceMode::AddedToLens->value], true))
                    ->required()->numeric()->minValue(0)
                    ->maxValue(fn (Get $get): ?int => $get('price_mode') === KitPriceMode::DiscountPercent->value ? 100 : null)
                    // Money is integer COP; only a discount may carry decimals.
                    ->rule('integer', fn (Get $get): bool => $get('price_mode') === KitPriceMode::AddedToLens->value)
                    ->live(onBlur: true),
                Toggle::make('is_optional')->label(__('app.onboarding.combos.fields.is_optional'))->live(),
                Toggle::make('is_preselected')->label(__('app.onboarding.combos.fields.is_preselected'))
                    ->default(true)
                    ->visible(fn (Get $get): bool => (bool) $get('is_optional'))
                    ->live(),
                ...($trigger === KitTrigger::Sale ? [
                    Select::make('upgrade_product_id')->label(__('app.onboarding.combos.fields.upgrade_product'))
                        ->options(fn (Get $get) => Product::query()->counter()->where('product_category_id', $get('slot_category_id'))->orderBy('name')->pluck('name', 'id')),
                    TextInput::make('upgrade_min_total')->label(__('app.onboarding.combos.fields.upgrade_min_total'))
                        ->integer()->minValue(0)->prefix('$')
                        ->requiredWith('upgrade_product_id'),
                ] : []),
            ])
            // The same category may repeat across products, but not twice for one product.
            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                $pairs = collect($value)->map(fn (array $row): string => ($row['trigger_product_id'] ?? '').':'.($row['slot_category_id'] ?? ''));
                if ($pairs->duplicates()->isNotEmpty()) {
                    $fail(__('app.onboarding.combos.duplicate_category'));
                }
            }, $isProductCombo)
            ->columns(3)
            ->defaultItems(0)
            ->addActionLabel(__('app.onboarding.combos.add'));
    }

    /**
     * One line per slot, as the seller will see it in the POS.
     *
     * @param  array<string, array<string, mixed>>  $slots
     */
    private function preview(array $slots): string
    {
        $categories = ProductCategory::query()->whereIn('id', array_filter(array_column($slots, 'slot_category_id')))->pluck('name', 'id');
        $products = Product::query()->whereIn('id', array_filter(array_column($slots, 'default_product_id')))->get()->keyBy('id');

        return collect($slots)
            ->filter(fn (array $slot): bool => isset($categories[$slot['slot_category_id'] ?? null], $products[$slot['default_product_id'] ?? null]))
            ->map(function (array $slot) use ($categories, $products): string {
                $product = $products[$slot['default_product_id']];
                $checked = ! ($slot['is_optional'] ?? false) || ($slot['is_preselected'] ?? true);
                $value = (float) ($slot['price_value'] ?? 0);
                $price = match (KitPriceMode::tryFrom($slot['price_mode'] ?? '')) {
                    KitPriceMode::AddedToLens => __('app.pos.kit.added_to_lens', ['amount' => $this->money((int) $value)]),
                    KitPriceMode::Normal => $this->money($product->price),
                    KitPriceMode::DiscountPercent => $this->money((int) round($product->price * (100 - $value) / 100)),
                    default => __('app.pos.kit.gift'),
                };

                return e(($checked ? '☑' : '☐')." {$categories[$slot['slot_category_id']]}: {$product->name} — {$price}");
            })
            ->join('<br>');
    }

    private function money(int $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }
}
