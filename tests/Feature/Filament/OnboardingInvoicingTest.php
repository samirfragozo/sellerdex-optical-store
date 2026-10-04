<?php

use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Enums\LayawayInvoicing;
use App\Filament\Pages\Onboarding;
use App\Filament\Pages\Onboarding\Steps\InvoicingStep;
use App\Models\Company;
use App\Models\NumberingRange;
use App\Models\User;
use Livewire\Livewire;

function invoicingStepAdmin(): User
{
    $company = Company::factory()->notOnboarded()->create([
        'tax_id' => '900', 'invoicing_mode' => InvoicingMode::Undecided->value, 'onboarding_step' => InvoicingStep::key(),
    ]);
    $admin = User::factory()->forCompany($company)->admin()->create();
    test()->actingAs($admin);

    return $admin;
}

it('saves receipt-only mode with the receipt prefix', function () {
    $admin = invoicingStepAdmin();

    Livewire::test(Onboarding::class)
        ->assertSet('step', InvoicingStep::key())
        ->set('data.invoicing_mode', InvoicingMode::ReceiptOnly->value)
        ->set('data.receipt_prefix', 'R-')
        ->set('data.layaway_invoicing', LayawayInvoicing::OnSale->value)
        ->call('next')
        ->assertHasNoErrors();

    $company = $admin->company->fresh();
    $range = NumberingRange::withoutGlobalScopes()->where('company_id', $company->id)
        ->where('document_type', FiscalDocumentType::Receipt->value)->sole();
    expect($company->invoicing_mode)->toBe(InvoicingMode::ReceiptOnly)
        ->and($company->layaway_invoicing)->toBe(LayawayInvoicing::OnSale)
        ->and($range->prefix)->toBe('R-')
        ->and($range->next_number)->toBe(1)
        ->and($range->is_active)->toBeTrue();
});

it('saves external-manual mode with the default document and resolution expiry dates', function () {
    $admin = invoicingStepAdmin();
    $expiry = today()->addMonths(6);

    Livewire::test(Onboarding::class)
        ->set('data.invoicing_mode', InvoicingMode::ExternalManual->value)
        ->set('data.default_fiscal_document', FiscalDocumentType::ElectronicInvoice->value)
        ->set('data.pos_resolution_expires_at', $expiry->toDateString())
        ->call('next')
        ->assertHasNoErrors();

    $company = $admin->company->fresh();
    expect($company->invoicing_mode)->toBe(InvoicingMode::ExternalManual)
        ->and($company->default_fiscal_document)->toBe(FiscalDocumentType::ElectronicInvoice)
        ->and($company->pos_resolution_expires_at->toDateString())->toBe($expiry->toDateString())
        ->and($company->invoice_resolution_expires_at)->toBeNull();
});

it('refuses to leave the step undecided', function () {
    $admin = invoicingStepAdmin();

    Livewire::test(Onboarding::class)
        ->set('data.invoicing_mode', InvoicingMode::Undecided->value)
        ->call('next')
        ->assertHasErrors(['data.invoicing_mode'])
        ->assertSet('step', InvoicingStep::key());

    expect($admin->company->fresh()->invoicing_mode)->toBe(InvoicingMode::Undecided);
});
