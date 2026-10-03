<?php

namespace App\Http\Middleware;

use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Support\Readiness\ReadinessIssue;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'business' => [
                'name' => rescue(fn (): string => Company::current()->name, config('app.name'), report: false),
            ],
            'auth' => [
                'user' => $request->user(),
                'is_admin' => (bool) $request->user()?->isAdmin(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'cashRegisterSession' => function () use ($request): ?array {
                $session = CashRegisterSession::openFor($request->user());

                return $session === null ? null : [
                    ...$session->only(['id', 'opened_at', 'opening_cash', 'closed_at', 'closed_cash', 'expected_cash', 'difference']),
                    'is_stale' => $session->isStale(),
                ];
            },
            // A blind close freezes the counts first; the cashier still owes the note before opening again.
            'pendingCashNote' => function () use ($request): ?array {
                $last = $request->user() === null ? null : CashRegisterSession::query()
                    ->where('user_id', $request->user()->id)
                    ->whereNotNull('closed_at')
                    ->latest('closed_at')
                    ->first();

                return $last?->needsNote() ? ['id' => $last->id, 'counts' => $last->countsSummary()] : null;
            },
            'suggestedOpeningCash' => fn (): int => $request->user()?->company === null ? 0 : CashRegisterSession::suggestedOpeningCash($request->user()->company),
            'readiness' => fn (): array => $request->user()?->company_id === null
                ? []
                : array_map(fn (ReadinessIssue $issue): array => $issue->forViewer($request->user())->toArray(), Company::current()->saleReadiness()),
            'translations' => [
                'auth' => trans('auth'),
                'settings' => trans('settings'),
                'app' => trans('app'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
