<?php

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('streams the attachment to a user of the same company', function () {
    $seller = User::factory()->seller()->create();
    $this->actingAs($seller);
    Storage::disk('local')->put('prescriptions/rx.jpg', 'image-bytes');
    $rx = Prescription::factory()->create(['attachment' => 'prescriptions/rx.jpg']);

    $response = $this->get(route('documents.prescription.attachment', $rx));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', 'sandbox');
    expect($response->streamedContent())->toBe('image-bytes');
});

it('never serves another company\'s attachment', function () {
    $owner = User::factory()->seller()->create();
    $this->actingAs($owner);
    Storage::disk('local')->put('prescriptions/rx.jpg', 'image-bytes');
    $rx = Prescription::factory()->create(['attachment' => 'prescriptions/rx.jpg']);

    $response = $this->actingAs(User::factory()->seller()->create())
        ->get(route('documents.prescription.attachment', $rx));

    expect($response->status())->toBeIn([403, 404]);
});

it('returns 404 when the prescription has no attachment', function () {
    $this->actingAs(User::factory()->seller()->create());
    $rx = Prescription::factory()->create(['attachment' => null]);

    $this->get(route('documents.prescription.attachment', $rx))->assertNotFound();
});

it('deletes the previous file when the attachment is replaced or cleared', function () {
    $this->actingAs(User::factory()->seller()->create());
    Storage::disk('local')->put('prescriptions/old.jpg', 'old');
    Storage::disk('local')->put('prescriptions/new.jpg', 'new');
    $rx = Prescription::factory()->create(['attachment' => 'prescriptions/old.jpg']);

    $rx->update(['attachment' => 'prescriptions/new.jpg']);

    Storage::disk('local')->assertMissing('prescriptions/old.jpg');
    Storage::disk('local')->assertExists('prescriptions/new.jpg');

    $rx->update(['attachment' => null]);

    Storage::disk('local')->assertMissing('prescriptions/new.jpg');
});

it('keeps the file on soft delete and removes it on force delete', function () {
    $this->actingAs(User::factory()->seller()->create());
    Storage::disk('local')->put('prescriptions/rx.jpg', 'image-bytes');
    $rx = Prescription::factory()->create(['attachment' => 'prescriptions/rx.jpg']);

    $rx->delete();
    Storage::disk('local')->assertExists('prescriptions/rx.jpg');

    $rx->forceDelete();
    Storage::disk('local')->assertMissing('prescriptions/rx.jpg');
});
