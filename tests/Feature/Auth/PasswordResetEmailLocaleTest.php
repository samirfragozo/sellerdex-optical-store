<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password email is sent in spanish', function () {
    Notification::fake();
    app()->setLocale('es');

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe(__('mail.reset_password.subject', [], 'es'))
            ->and($mail->introLines)->toContain(__('mail.reset_password.line_1', [], 'es'));

        return true;
    });
});

test('reset password email is sent in english', function () {
    Notification::fake();
    app()->setLocale('en');

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe(__('mail.reset_password.subject', [], 'en'))
            ->and($mail->introLines)->toContain(__('mail.reset_password.line_1', [], 'en'));

        return true;
    });
});
