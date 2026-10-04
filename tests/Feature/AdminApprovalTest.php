<?php

use App\Models\User;
use App\Support\AdminApproval;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['approval_pin' => '4321']);
    $this->seller = User::factory()->seller()->create(['company_id' => $this->admin->company_id]);
    $this->actingAs($this->seller);
});

it('lets an admin approve their own actions without a pin', function () {
    expect(AdminApproval::approver($this->admin, null)->is($this->admin))->toBeTrue();
});

it('resolves the admin whose pin matches', function () {
    expect(AdminApproval::approver($this->seller, '4321')->is($this->admin))->toBeTrue();
});

it('returns no approver without a pin and ensure() asks for one', function () {
    expect(AdminApproval::approver($this->seller, ''))->toBeNull();

    expect(fn () => AdminApproval::ensure(null))->toThrow(ValidationException::class, __('app.approval.required'));
});

it('refuses a wrong pin', function () {
    expect(fn () => AdminApproval::approver($this->seller, '0000'))->toThrow(ValidationException::class, __('app.approval.invalid'));
});

it('never accepts the pin of a non-admin or of another company', function () {
    User::factory()->seller()->create(['company_id' => $this->admin->company_id, 'approval_pin' => '1111']);
    User::factory()->admin()->create(['approval_pin' => '2222']);

    expect(fn () => AdminApproval::approver($this->seller, '1111'))->toThrow(ValidationException::class)
        ->and(fn () => AdminApproval::approver($this->seller, '2222'))->toThrow(ValidationException::class);
});

it('locks approvals after five wrong pins', function () {
    foreach (range(1, 5) as $attempt) {
        try {
            AdminApproval::approver($this->seller, '0000');
        } catch (ValidationException) {
        }
    }

    try {
        AdminApproval::approver($this->seller, '4321');
        $this->fail('Expected the approval to be throttled.');
    } catch (ValidationException $e) {
        // The remaining seconds tick down, so only the fixed part of the message is compared.
        expect($e->errors()['approval_pin'][0])->toStartWith(Str::before(__('app.approval.throttled'), ':seconds'));
    }
});

it('stores the pin hashed and never serializes it', function () {
    expect($this->admin->getRawOriginal('approval_pin'))->not->toBe('4321')
        ->and($this->admin->toArray())->not->toHaveKey('approval_pin');
});
