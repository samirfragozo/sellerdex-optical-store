<?php

use App\Enums\MessageTemplateKey;
use App\Enums\SaleDocumentType;
use App\Filament\Pages\FollowUp;
use App\Filament\Widgets\FollowUp\BalancesDueWidget;
use App\Filament\Widgets\FollowUp\BirthdaysWidget;
use App\Filament\Widgets\FollowUp\ExpiringPrescriptionsWidget;
use App\Models\Customer;
use App\Models\FollowUpContact;
use App\Models\MessageTemplate;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\WhatsApp;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
});

function followUpPrescription(Customer $customer, string $examDate, string $expiresAt): Prescription
{
    $prescription = Prescription::factory()->create([
        'customer_id' => $customer->id, 'company_id' => $customer->company_id,
        'exam_date' => $examDate,
    ]);
    // The model derives expires_at from exam_date on create; set it explicitly without events.
    $prescription->expires_at = $expiresAt;
    $prescription->saveQuietly();

    return $prescription;
}

it('opens the follow-up page for a seller', function () {
    $this->get(FollowUp::getUrl())->assertOk();
});

it('lists the latest prescription expiring within 30 days or expired in the last 30', function () {
    $soon = Customer::factory()->create(['name' => 'Ana']);
    $soonRx = followUpPrescription($soon, now()->subMonths(11)->toDateString(), now()->addDays(10)->toDateString());
    $late = Customer::factory()->create(['name' => 'Beto']);
    $lateRx = followUpPrescription($late, now()->subMonths(13)->toDateString(), now()->subDays(20)->toDateString());
    $renewed = Customer::factory()->create(['name' => 'Carla']);
    $oldRx = followUpPrescription($renewed, now()->subMonths(12)->toDateString(), now()->toDateString());
    followUpPrescription($renewed, now()->subWeek()->toDateString(), now()->addMonths(11)->toDateString());
    $far = Customer::factory()->create(['name' => 'Dani']);
    $farRx = followUpPrescription($far, now()->subMonths(6)->toDateString(), now()->addMonths(6)->toDateString());

    Livewire::test(ExpiringPrescriptionsWidget::class)
        ->assertCanSeeTableRecords([$soonRx, $lateRx])
        ->assertCanNotSeeTableRecords([$oldRx, $farRx]);
});

it('drops a prescription once it is marked contacted', function () {
    $customer = Customer::factory()->create();
    $rx = followUpPrescription($customer, now()->subYear()->toDateString(), now()->addDays(5)->toDateString());

    Livewire::test(ExpiringPrescriptionsWidget::class)
        ->callAction(TestAction::make('markContacted')->table($rx))
        ->assertCanNotSeeTableRecords([$rx]);

    expect(FollowUpContact::sole())->customer_id->toBe($customer->id)->user_id->toBe($this->seller->id);
});

it('links WhatsApp with the expiring message and hides the button without a phone', function () {
    $withPhone = Customer::factory()->create(['phone' => '3001234567']);
    $rx = followUpPrescription($withPhone, now()->subYear()->toDateString(), now()->addDays(5)->toDateString());
    $noPhone = Customer::factory()->create(['phone' => null]);
    $rx2 = followUpPrescription($noPhone, now()->subYear()->toDateString(), now()->addDays(5)->toDateString());

    $expectedUrl = WhatsApp::url('3001234567', MessageTemplate::render($withPhone->company_id, MessageTemplateKey::PrescriptionExpiring, ['cliente' => $withPhone->name]));

    Livewire::test(ExpiringPrescriptionsWidget::class)
        ->assertActionVisible(TestAction::make('whatsApp')->table($rx))
        ->assertActionHasUrl(TestAction::make('whatsApp')->table($rx), $expectedUrl)
        ->assertActionHidden(TestAction::make('whatsApp')->table($rx2));
});

it('never lists another company prescriptions', function () {
    $foreign = User::factory()->admin()->create();
    $customer = Customer::factory()->create(['company_id' => $foreign->company_id]);
    $rx = Prescription::withoutGlobalScopes()->create([...Prescription::factory()->raw(), 'company_id' => $foreign->company_id, 'customer_id' => $customer->id, 'exam_date' => now()->subYear()->toDateString()]);
    $rx->expires_at = now()->addDays(3)->toDateString();
    $rx->saveQuietly();

    Livewire::test(ExpiringPrescriptionsWidget::class)->assertCanNotSeeTableRecords([$rx]);
});

it('does not list a prescription whose customer was deleted', function () {
    $customer = Customer::factory()->create();
    $rx = followUpPrescription($customer, now()->subYear()->toDateString(), now()->addDays(5)->toDateString());
    $customer->delete();

    Livewire::test(ExpiringPrescriptionsWidget::class)->assertCanNotSeeTableRecords([$rx]);
});

it('lists sales with a balance due and hides them for 7 days once contacted', function () {
    $customer = Customer::factory()->create(['phone' => '3001234567']);
    $due = Sale::factory()->create(['customer_id' => $customer->id]);
    SaleItem::factory()->create(['sale_id' => $due->id, 'quantity' => 1, 'unit_price' => 100_000]);
    $paid = Sale::factory()->create(['customer_id' => $customer->id]);
    SaleItem::factory()->create(['sale_id' => $paid->id, 'quantity' => 1, 'unit_price' => 50_000]);
    Payment::factory()->create(['sale_id' => $paid->id, 'amount' => 50_000]);
    $walkIn = Sale::factory()->create(['customer_id' => null]);
    SaleItem::factory()->create(['sale_id' => $walkIn->id, 'quantity' => 1, 'unit_price' => 30_000]);
    $quote = Sale::factory()->create(['customer_id' => $customer->id, 'document_type' => SaleDocumentType::Quote->value]);
    SaleItem::factory()->create(['sale_id' => $quote->id, 'quantity' => 1, 'unit_price' => 20_000]);
    $goneCustomer = Customer::factory()->create();
    $orphan = Sale::factory()->create(['customer_id' => $goneCustomer->id]);
    SaleItem::factory()->create(['sale_id' => $orphan->id, 'quantity' => 1, 'unit_price' => 40_000]);
    $goneCustomer->delete();

    Livewire::test(BalancesDueWidget::class)
        ->assertCanSeeTableRecords([$due->fresh()])
        ->assertCanNotSeeTableRecords([$paid->fresh(), $walkIn->fresh(), $quote->fresh(), $orphan->fresh()])
        ->callAction(TestAction::make('markContacted')->table($due->fresh()))
        ->assertCanNotSeeTableRecords([$due->fresh()]);

    $this->travel(8)->days();
    Livewire::test(BalancesDueWidget::class)->assertCanSeeTableRecords([$due->fresh()]);
});

it('lists today\'s birthdays until contacted this year', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    $birthday = Customer::factory()->create(['birth_date' => '1996-06-15', 'phone' => '3001234567']);
    $other = Customer::factory()->create(['birth_date' => '1996-06-16']);

    Livewire::test(BirthdaysWidget::class)
        ->assertCanSeeTableRecords([$birthday])
        ->assertCanNotSeeTableRecords([$other])
        ->callAction(TestAction::make('markContacted')->table($birthday))
        ->assertCanNotSeeTableRecords([$birthday]);
});
