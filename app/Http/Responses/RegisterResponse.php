<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsAfterAuth;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    use RedirectsAfterAuth;

    public function toResponse($request): mixed
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return $this->redirectAfterAuth(Fortify::redirects('register'));
    }
}
