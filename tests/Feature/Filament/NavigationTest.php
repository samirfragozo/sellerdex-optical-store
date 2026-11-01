<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @return array<string, list<string>> group label ('' when ungrouped) => item labels, in sidebar order
 */
function sidebarFor(User $user): array
{
    test()->actingAs($user)->get('/admin')->assertSuccessful();

    return collect(Filament::getPanel('admin')->getNavigation())
        ->mapWithKeys(fn (NavigationGroup $group): array => [
            (string) $group->getLabel() => collect($group->getItems())->map(fn (NavigationItem $item): string => (string) $item->getLabel())->values()->all(),
        ])
        ->all();
}

it('groups the admin sidebar by usage, most visited first', function () {
    $admin = User::factory()->admin()->create();
    $admin->company->update(['tracks_inventory' => true]);

    expect(sidebarFor($admin))->toBe([
        '' => [__('filament-panels::pages/dashboard.title')],
        __('app.navigation_groups.counter') => [
            __('app.resources.sale.nav'),
            __('app.resources.lens_order.nav'),
            __('app.resources.customer.nav'),
            __('app.resources.prescription.nav'),
            __('app.follow_up.title'),
            __('app.resources.warranty_claim.nav'),
        ],
        __('app.navigation_groups.finance') => [
            __('app.resources.cash_register_session.nav'),
            __('app.resources.cash_close.nav'),
            __('app.clusters.gastos'),
            __('app.reports.title'),
        ],
        __('app.navigation_groups.inventory') => [
            __('app.clusters.catalogo'),
            __('app.clusters.compras'),
            __('app.inventory.initial_count'),
        ],
        __('app.navigation_groups.settings') => [
            __('app.business.title'),
            __('app.resources.user.nav'),
            __('app.resources.payment_method.nav'),
            __('app.resources.tax.nav'),
            __('app.resources.combo_settings.nav'),
        ],
    ]);
});

it('hides admin-only entries and empty groups from a seller', function () {
    $sidebar = sidebarFor(User::factory()->seller()->create());

    expect($sidebar)->toHaveKey(__('app.navigation_groups.counter'))
        ->and(collect($sidebar)->flatten()->all())->not->toContain(
            __('app.reports.title'),
            __('app.business.title'),
            __('app.resources.tax.nav'),
            __('app.resources.combo_settings.nav'),
        );
});

it('gives every sidebar and sub-navigation entry its own icon', function () {
    $panel = Filament::getPanel('admin');

    $icons = collect([...$panel->getResources(), ...$panel->getPages(), ...$panel->getClusters()])
        ->filter(fn (string $class): bool => $class::getNavigationIcon() !== null)
        ->mapWithKeys(fn (string $class): array => [$class => $class::getNavigationIcon()]);

    expect($icons->duplicates()->all())->toBe([]);
});
