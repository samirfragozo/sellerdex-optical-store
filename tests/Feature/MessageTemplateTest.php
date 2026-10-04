<?php

use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->admin->company->update(['name' => 'Óptica Central']);
});

it('falls back to the translated default body', function () {
    expect(MessageTemplate::bodyFor($this->admin->company_id, MessageTemplateKey::OrderReady))
        ->toBe(__('app.message_templates.defaults.order_ready'));
});

it('renders the default in the app locale, not the staff interface locale', function () {
    config(['app.customer_locale' => 'es']);
    app()->setLocale('en');

    expect(MessageTemplate::bodyFor($this->admin->company_id, MessageTemplateKey::OrderReady))
        ->toBe(__('app.message_templates.defaults.order_ready', [], 'es'))
        ->not->toBe(__('app.message_templates.defaults.order_ready', [], 'en'));
});

it('renders the stored body with the placeholders filled', function () {
    MessageTemplate::create(['company_id' => $this->admin->company_id, 'key' => MessageTemplateKey::BalanceDue, 'body' => 'Hola {cliente}, la orden {orden} tiene saldo {saldo}. {optica}']);

    expect(MessageTemplate::render($this->admin->company_id, MessageTemplateKey::BalanceDue, ['cliente' => 'Ana', 'orden' => '000123', 'saldo' => 85_000]))
        ->toBe('Hola Ana, la orden 000123 tiene saldo $85.000. Óptica Central');
});

it('leaves no raw placeholder when a value is missing', function () {
    MessageTemplate::create(['company_id' => $this->admin->company_id, 'key' => MessageTemplateKey::Birthday, 'body' => '¡Feliz cumpleaños {cliente}! {orden}']);

    expect(MessageTemplate::render($this->admin->company_id, MessageTemplateKey::Birthday, ['cliente' => 'Luis']))->toBe('¡Feliz cumpleaños Luis! ');
});

it('keeps each company templates apart', function () {
    $other = User::factory()->admin()->create();
    MessageTemplate::withoutGlobalScopes()->create(['company_id' => $other->company_id, 'key' => MessageTemplateKey::OrderReady->value, 'body' => 'Otra']);

    expect(MessageTemplate::bodyFor($this->admin->company_id, MessageTemplateKey::OrderReady))->not->toBe('Otra');
});
