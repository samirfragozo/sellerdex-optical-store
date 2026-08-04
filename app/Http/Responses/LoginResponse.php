<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsAfterAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    use RedirectsAfterAuth;

    public function toResponse($request): mixed
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return $this->redirectAfterAuth($this->defaultRedirectFor($request));
    }

    private function defaultRedirectFor(Request $request): string
    {
        /** @var User $user */
        $user = $request->user();

        return match (true) {
            $user->hasRole(User::ROLE_SUPERADMIN) => '/superadmin',
            $user->hasRole(User::ROLE_ADMIN) => '/admin',
            default => '/pos',
        };
    }
}
