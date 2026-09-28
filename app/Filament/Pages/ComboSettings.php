<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Onboarding\Steps\CombosStep;
use App\Models\Company;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/** The onboarding combos step as a settings page, plus the per-product combos. */
class ComboSettings extends Page
{
    protected string $view = 'filament.pages.combo-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('app.resources.combo_settings.nav');
    }

    public function getTitle(): string
    {
        return __('app.resources.combo_settings.nav');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    /** Unlike the onboarding step, the reference combo is not reinstalled here: the admin may want none. */
    public function mount(): void
    {
        $this->form->fill(Arr::only(Company::current()->attributesToArray(), ['armado_frame_price_mode', 'armado_frame_discount_percent']));
    }

    public function form(Schema $schema): Schema
    {
        $step = app(CombosStep::class);

        return $schema
            ->statePath('data')
            ->model(Company::current())
            ->components([...$step->components(), $step->productSection()]);
    }

    public function save(): void
    {
        // getState() also saves the repeaters' kit-slot relationships.
        app(CombosStep::class)->save(Company::current(), $this->form->getState());

        Notification::make()->success()->title(__('app.business.saved'))->send();
    }
}
