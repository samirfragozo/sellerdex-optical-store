<?php

namespace App\Filament\Pages;

use App\Models\BusinessSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class BusinessSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected string $view = 'filament.pages.business-settings-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('app.business.title');
    }

    public function getTitle(): string
    {
        return __('app.business.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public function mount(): void
    {
        $this->form->fill(BusinessSetting::current()->only(['name', 'tax_id', 'address', 'phones', 'logo']));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required(),
                TextInput::make('tax_id')
                    ->label(__('app.fields.tax_id')),
                TextInput::make('address')
                    ->label(__('app.fields.address')),
                TextInput::make('phones')
                    ->label(__('app.fields.phone')),
                FileUpload::make('logo')
                    ->label(__('app.fields.logo'))
                    ->image()
                    ->disk('public')
                    ->directory('business'),
            ]);
    }

    public function save(): void
    {
        BusinessSetting::current()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title(__('app.business.saved'))
            ->send();
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('app.business.saved'))
                ->submit('save'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->key('form-actions'),
                    ]),
            ]);
    }
}
