<?php

use App\Filament\Pages\LensOnboarding;
use App\Models\LensCombination;
use App\Models\LensType;
use App\Models\User;
use App\Support\ReferenceLensCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates every default combination and marks the company onboarded', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(LensOnboarding::class)->call('submit');

    expect(LensCombination::count())->toBe(count(ReferenceLensCatalog::combinations()))
        ->and($admin->company->fresh()->lens_onboarding_completed_at)->not->toBeNull();
});

it('only creates the combinations the user kept selected', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(LensOnboarding::class)
        ->set('data.selected_combo_keys', ['monofocal-standard-cr39'])
        ->call('submit');

    expect(LensCombination::count())->toBe(1)
        ->and(LensType::where('name', 'Monofocal')->exists())->toBeTrue()
        ->and(LensType::where('name', 'Progresivo')->exists())->toBeFalse();
});

it('uses the cost and price the user typed instead of the suggested defaults', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(LensOnboarding::class)
        ->set('data.selected_combo_keys', ['monofocal-standard-cr39'])
        ->set('data.pricing.monofocal-standard-cr39.cost', 40000)
        ->set('data.pricing.monofocal-standard-cr39.price', 120000)
        ->set('data.pricing.monofocal-standard-cr39.installation_price', 20000)
        ->call('submit');

    $combination = LensCombination::sole();

    expect($combination->cost)->toBe(40000)
        ->and($combination->price)->toBe(120000)
        ->and($combination->installation_price)->toBe(20000);
});

it('requires at least one selected combination to submit', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(LensOnboarding::class)
        ->set('data.selected_combo_keys', [])
        ->call('submit')
        ->assertHasErrors(['data.selected_combo_keys' => 'required']);

    expect(LensCombination::count())->toBe(0);
});

it('skipping marks the company onboarded without creating any combination', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(LensOnboarding::class)->call('skip');

    expect(LensCombination::count())->toBe(0)
        ->and($admin->company->fresh()->lens_onboarding_completed_at)->not->toBeNull();
});
