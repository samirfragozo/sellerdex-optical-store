<?php

use App\Enums\MessageTemplateKey;
use App\Filament\Pages\FollowUp;
use App\Filament\Widgets\FollowUp\ExpiringPrescriptionsWidget;
use App\Models\Customer;
use App\Models\FollowUpContact;
use App\Models\MessageTemplate;
use App\Models\Prescription;
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
