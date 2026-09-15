<?php

namespace App\Providers;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLanguageSwitch();
        $this->configureMail();
    }

    /**
     * Send system emails translated into the currently active locale.
     */
    protected function configureMail(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $expireMinutes = config('auth.passwords.users.expire');

            return (new MailMessage)
                ->subject(__('mail.reset_password.subject'))
                ->greeting(__('mail.reset_password.greeting'))
                ->line(__('mail.reset_password.line_1'))
                ->action(__('mail.reset_password.action'), $url)
                ->line(__('mail.reset_password.line_2', ['count' => $expireMinutes]))
                ->line(__('mail.reset_password.line_3'));
        });
    }

    /**
     * Languages available in the panel (Spanish by default, English as alternative).
     */
    protected function configureLanguageSwitch(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                ->locales(config('app.supported_locales'))
                ->visible(outsidePanels: true);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        URL::forceScheme('https');

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
