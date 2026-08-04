<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

trait RedirectsAfterAuth
{
    /**
     * Redirect to the intended URL (or a default), doing a full browser
     * visit via Inertia::location() when the target is a Filament panel
     * (not an Inertia page) so it doesn't get rendered inside Inertia's
     * "not an Inertia response" modal.
     */
    protected function redirectAfterAuth(string $default): Response
    {
        $url = session()->pull('url.intended', $default);

        return Str::startsWith($url, ['/admin', '/superadmin'])
            ? Inertia::location($url)
            : redirect()->to($url);
    }
}
