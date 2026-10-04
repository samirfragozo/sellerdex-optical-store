<?php

namespace App\Filament\Widgets\FollowUp;

use App\Enums\FollowUpReason;
use App\Models\Customer;
use App\Models\FollowUpContact;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** The two buttons every follow-up row shows: open WhatsApp, and mark the customer as contacted. */
final class FollowUpActions
{
    /** @param  Closure(Model): ?string  $url */
    public static function whatsApp(Closure $url): Action
    {
        return Action::make('whatsApp')
            ->label(__('app.follow_up.whatsapp'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (Model $record): bool => $url($record) !== null)
            ->url(fn (Model $record): ?string => $url($record))
            ->openUrlInNewTab();
    }

    /**
     * @param  Closure(Model): ?Customer  $customer
     * @param  Closure(Model): ?Model  $subject
     */
    public static function markContacted(FollowUpReason $reason, Closure $customer, Closure $subject): Action
    {
        return Action::make('markContacted')
            ->label(__('app.follow_up.mark_contacted'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('gray')
            ->visible(fn (Model $record): bool => $customer($record) !== null)
            ->action(function (Model $record) use ($reason, $customer, $subject): void {
                FollowUpContact::record($customer($record), $reason, $subject($record));
                Notification::make()->success()->title(__('app.follow_up.contacted'))->send();
            });
    }
}
