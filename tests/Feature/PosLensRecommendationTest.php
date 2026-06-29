<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns recommendations and warnings for a prescription', function () {
    $user = User::factory()->seller()->create();

    $response = $this->actingAs($user)->postJson('/pos/lens-recommendation', [
        'prescription' => ['od_add' => '2.00', 'od_sphere' => '-1.00'],
        'chosen' => ['design' => 'Monofocal'],
    ]);

    $response->assertOk()
        ->assertJsonPath('recommended.design', 'Progresivo')
        ->assertJsonPath('warnings.0', 'La fórmula tiene adición; suele requerir un lente bifocal o progresivo.');
});

it('returns empty warnings when no chosen config is sent', function () {
    $user = User::factory()->seller()->create();

    $response = $this->actingAs($user)->postJson('/pos/lens-recommendation', [
        'prescription' => ['od_sphere' => '-1.00'],
    ]);

    $response->assertOk()
        ->assertJsonPath('recommended.design', 'Monofocal')
        ->assertJsonPath('warnings', []);
});

it('requires authentication', function () {
    $this->postJson('/pos/lens-recommendation', ['prescription' => []])
        ->assertUnauthorized();
});
