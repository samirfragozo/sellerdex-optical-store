<?php

use App\Models\LensTreatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea tratamientos de lente con precio y costo', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $record = LensTreatment::factory()->create(['name' => 'Antirreflejo', 'price' => 50000, 'cost' => 20000]);

    expect($record->company_id)->toBe($user->company_id)
        ->and($record->price)->toBe(50000)
        ->and($record->cost)->toBe(20000);
});
