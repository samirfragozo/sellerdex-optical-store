<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Onboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keeps a company inside its onboarding until it is ready to sell. */
class EnsureCompanyIsOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Superadmin (no company) and onboarded companies pass.
        if ($user?->company_id === null || ! $user->company->needsOnboarding()) {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.pages.onboarding', 'filament.admin.auth.logout')) {
            return $next($request);
        }

        abort_unless($user->isAdmin(), 403, __('app.onboarding.pending_for_staff'));

        return redirect()->to(Onboarding::getUrl());
    }
}
