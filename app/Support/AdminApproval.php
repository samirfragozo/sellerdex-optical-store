<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Who approves what a user does beyond their role: an admin approves themselves, anyone else needs an
 * active admin of the same company to type their PIN. Call it outside DB transactions: the throttle
 * lives in the cache, which is the database in production, and a rollback would erase the misses.
 */
final class AdminApproval
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public static function approver(User $actor, ?string $pin, string $field = 'approval_pin'): ?User
    {
        if ($actor->isAdmin()) {
            return $actor;
        }

        if (blank($pin)) {
            return null;
        }

        $key = 'approval-pin:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([$field => __('app.approval.throttled', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        // ponytail: one hash check per PIN holder; a shop has a handful of admins.
        $admin = User::query()
            ->where('company_id', $actor->company_id)
            ->where('is_active', true)
            ->whereNotNull('approval_pin')
            ->get()
            ->first(fn (User $user): bool => Hash::check((string) $pin, $user->approval_pin) && $user->isAdmin());

        if ($admin === null) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([$field => __('app.approval.invalid')]);
        }

        RateLimiter::clear($key);

        return $admin;
    }

    public static function ensure(?User $approver, string $field = 'approval_pin'): User
    {
        return $approver ?? throw ValidationException::withMessages([$field => __('app.approval.required')]);
    }
}
