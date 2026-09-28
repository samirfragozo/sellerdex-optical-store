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

    $this->get(route('documents.prescription.attachment', $rx))->assertOk();
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
