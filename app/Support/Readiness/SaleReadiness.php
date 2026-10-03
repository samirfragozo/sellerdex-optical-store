<?php

namespace App\Support\Readiness;

use App\Enums\ReadinessSeverity;
use App\Filament\Pages\ComboSettings;
use App\Filament\Resources\LensCombinations\LensCombinationResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Company;
use App\Models\KitSlot;
use App\Models\LensCombinationPrice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Scopes\CompanyScope;

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

        return $issues;
    }
}
