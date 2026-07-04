<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // superadmin (no company) — siempre pasa
        if ($user === null || $user->company_id === null) {
            return $next($request);
        }

        if (! $user->company?->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Tu cuenta ha sido suspendida. Contacta al soporte.');
        }

        return $next($request);
    }
}
