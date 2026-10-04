<?php

namespace App\Support\Readiness;

use App\Enums\InvoicingMode;
use App\Enums\ReadinessSeverity;
use App\Filament\Pages\ComboSettings;
use App\Filament\Pages\InventoryCount;
use App\Filament\Resources\BusinessSettings\BusinessSettingResource;
use App\Filament\Resources\CashRegisterSessions\CashRegisterSessionResource;
use App\Filament\Resources\LensCombinations\LensCombinationResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\KitSlot;
use App\Models\LensCombinationPrice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Support\PermissionsTeam;

/**
 * The single place that decides whether a company can sell. Queries bypass the
 * auth-based CompanyScope and filter by the given company explicitly, so the
 * result never depends on who is logged in.
 */
class SaleReadiness
{
    /** @return list<ReadinessIssue> */
    public static function for(Company $company): array
    {
        $issues = [];

        $hasPaymentMethod = PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $company->id)->where('is_active', true)->exists();
        if (! $hasPaymentMethod) {
            $issues[] = new ReadinessIssue(
                'payment_method', ReadinessSeverity::Blocking,
                __('app.readiness.payment_method'), PaymentMethodResource::getUrl('index', panel: 'admin'),
            );
        }

        $activeLabs = Supplier::withoutGlobalScopes()
            ->where('company_id', $company->id)->where('is_laboratory', true)->where('is_active', true);
        if (! (clone $activeLabs)->exists()) {
            $issues[] = new ReadinessIssue(
                'laboratory', ReadinessSeverity::Blocking,
                __('app.readiness.laboratory'), SupplierResource::getUrl('index', panel: 'admin'), 'lens',
            );
        } elseif ((clone $activeLabs)->whereNull('lead_time_days')->exists()) {
            $issues[] = new ReadinessIssue(
                'laboratory_lead_time', ReadinessSeverity::Warning,
                __('app.readiness.laboratory_lead_time'), SupplierResource::getUrl('index', panel: 'admin'),
            );
        }

        $hasPricedLens = LensCombinationPrice::withoutGlobalScopes()
            ->where('company_id', $company->id)->where('is_active', true)->where('price', '>', 0)
            ->whereHas('lensCombination', fn ($query) => $query->withoutGlobalScopes()->where('is_active', true))
            ->whereHas('supplier', fn ($query) => $query->withoutGlobalScope(CompanyScope::class)->where('is_laboratory', true)->where('is_active', true))
            ->exists();
        if (! $hasPricedLens) {
            $issues[] = new ReadinessIssue(
                'lens_price', ReadinessSeverity::Blocking,
                __('app.readiness.lens_price'), LensCombinationResource::getUrl('index', panel: 'admin'), 'lens',
            );
        }

        // A slot whose default product is inactive or deleted silently gives nothing.
        $hasBrokenCombo = KitSlot::withoutGlobalScopes()
            ->where('company_id', $company->id)->where('is_active', true)
            ->whereNotIn('default_product_id', Product::withoutGlobalScopes()->select('id')
                ->where('company_id', $company->id)->where('is_active', true)->whereNull('deleted_at'))
            ->exists();
        if ($hasBrokenCombo) {
            $issues[] = new ReadinessIssue(
                'combo_product_inactive', ReadinessSeverity::Warning,
                __('app.readiness.combo_product_inactive'), ComboSettings::getUrl(panel: 'admin'),
            );
        }

        // Without a stockable product the count page has nothing to offer, so the warning could never clear.
        $needsInitialCount = $company->tracks_inventory === true && $company->inventory_counted_at === null
            && Product::withoutGlobalScopes()->where('company_id', $company->id)
                ->where('is_active', true)->where('is_stockable', true)->whereNull('deleted_at')->exists();
        if ($needsInitialCount) {
            $issues[] = new ReadinessIssue(
                'inventory_initial_count', ReadinessSeverity::Warning,
                __('app.readiness.inventory_initial_count'), InventoryCount::getUrl(panel: 'admin'),
            );
        }

        $hasStaleSession = CashRegisterSession::withoutGlobalScopes()
            ->where('company_id', $company->id)->whereNull('closed_at')->where('opened_at', '<', today())->exists();
        if ($hasStaleSession) {
            $issues[] = new ReadinessIssue(
                'cash_session_stale', ReadinessSeverity::Warning,
                __('app.readiness.cash_session_stale'), CashRegisterSessionResource::getUrl('index', panel: 'admin'),
            );
        }

        // Sellers need an admin PIN for discounts above the cap and for returns; a one-person shop approves itself.
        $users = User::query()->where('company_id', $company->id)->where('is_active', true);
        // A demoted admin keeps a stale hash, so only PINs held by admins count (roles are team-scoped per company).
        $hasAdminPin = fn (): bool => PermissionsTeam::runAs($company, fn (): bool => (clone $users)->whereNotNull('approval_pin')->role(User::ROLE_ADMIN)->exists());
        if ((clone $users)->count() > 1 && ! $hasAdminPin()) {
            $issues[] = new ReadinessIssue(
                'approval_pin_missing', ReadinessSeverity::Warning,
                __('app.readiness.approval_pin_missing'), UserResource::getUrl('index', panel: 'admin'),
            );
        }

        if ($company->invoicing_mode === InvoicingMode::Undecided) {
            $issues[] = new ReadinessIssue(
                'invoicing_mode', ReadinessSeverity::Blocking,
                __('app.readiness.invoicing_mode'), BusinessSettingResource::getUrl('index', panel: 'admin'),
            );
        }

        if ($company->invoicing_mode === InvoicingMode::ExternalManual) {
            $dates = collect([$company->pos_resolution_expires_at, $company->invoice_resolution_expires_at])->filter();
            if ($dates->contains(fn ($date): bool => $date->lt(today()))) {
                $issues[] = new ReadinessIssue('resolution_expired', ReadinessSeverity::Warning, __('app.readiness.resolution_expired'), BusinessSettingResource::getUrl('index', panel: 'admin'));
            } elseif ($dates->contains(fn ($date): bool => $date->lte(today()->addDays(30)))) {
                $issues[] = new ReadinessIssue('resolution_expiring', ReadinessSeverity::Warning, __('app.readiness.resolution_expiring'), BusinessSettingResource::getUrl('index', panel: 'admin'));
            }
        }

        return $issues;
    }
}
