<?php

namespace App\Filament\Pages;

use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Product;
use App\Support\StockLedger;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** The physical count that opens the kardex: one number per stockable product, written as `initial` movements. */
class InventoryCount extends Page
{
    protected string $view = 'filament.pages.inventory-count';

    protected static ?string $slug = 'inventory-count';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('app.inventory.initial_count');
    }

    public function getTitle(): string
    {
        return __('app.inventory.initial_count');
    }

    public function getSubheading(): string
    {
        return __('app.inventory.count_help');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->company_id !== null && $user->isAdmin() && Company::current()->tracksInventory();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    /** @return Collection<int, Product> */
    public function products(): Collection
    {
        return Product::query()->with('category')->where('is_active', true)->where('is_stockable', true)->orderBy('name')->get();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'md' => 2])->schema(
                    $this->products()->map(fn (Product $product): TextInput => TextInput::make("counts.{$product->id}")
                        ->label($product->name)
                        ->helperText($product->category?->name)
                        ->placeholder((string) (int) $product->stock)
                        ->nullable()->integer()->minValue(0)
                    )->all(),
                ),
            ]);
    }

    public function save(): void
    {
        $counts = $this->form->getState()['counts'] ?? [];

        DB::transaction(function () use ($counts): void {
            foreach ($this->products() as $product) {
                $count = $counts[$product->id] ?? null;

                if (filled($count)) {
                    StockLedger::setBalance($product, (int) $count, StockMovementType::Initial, reason: __('app.inventory.initial_count'));
                }
            }

            Company::current()->update(['inventory_counted_at' => now()]);
        });

        Notification::make()->success()->title(__('app.inventory.counted_done'))->send();

        $this->redirect(Dashboard::getUrl());
    }
}
