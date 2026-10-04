<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FollowUp\ExpiringPrescriptionsWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Who to call today: prescriptions to renew, balances to collect and birthdays. */
class FollowUp extends Page
{
    protected string $view = 'filament.pages.follow-up';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'follow-up';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('app.follow_up.title');
    }

    public function getTitle(): string
    {
        return __('app.follow_up.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->company_id !== null;
    }

    protected function getHeaderWidgets(): array
    {
        return [ExpiringPrescriptionsWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
