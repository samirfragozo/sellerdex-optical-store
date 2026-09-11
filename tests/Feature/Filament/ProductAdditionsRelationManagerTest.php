<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\AdditionsRelationManager;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\AttachAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('attaches an addition with its own price and quantity from the product page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $host = Product::factory()->create();
    $addition = Product::factory()->create();

    Livewire::test(AdditionsRelationManager::class, [
        'ownerRecord' => $host,
        'pageClass' => EditProduct::class,
    ])
        ->callAction(TestAction::make(AttachAction::class)->table(), [
            'recordId' => $addition->id,
            'price' => -1000,
            'quantity' => 2,
            'is_active' => true,
        ])
        ->assertHasNoErrors();

    $attached = $host->additions()->first();

    expect($attached)->not->toBeNull()
        ->and($attached->id)->toBe($addition->id)
        ->and($attached->pivot->price)->toBe(-1000)
        ->and($attached->pivot->quantity)->toBe(2)
        ->and($attached->pivot->is_active)->toBeTrue();
});

it('rejects attaching a product as its own addition', function () {
    $this->actingAs(User::factory()->admin()->create());

    $host = Product::factory()->create();

    Livewire::test(AdditionsRelationManager::class, [
        'ownerRecord' => $host,
        'pageClass' => EditProduct::class,
    ])
        ->callAction(TestAction::make(AttachAction::class)->table(), [
            'recordId' => $host->id,
            'price' => 0,
            'quantity' => 1,
            'is_active' => true,
        ])
        ->assertHasErrors(['mountedActions.0.data.recordId']);

    expect($host->additions()->count())->toBe(0);
});

it('rejects attaching a product that is already attached as an addition', function () {
    $this->actingAs(User::factory()->admin()->create());

    $host = Product::factory()->create();
    $addition = Product::factory()->create();

    $host->additions()->attach($addition->id, ['price' => 0, 'quantity' => 1, 'is_active' => true]);

    Livewire::test(AdditionsRelationManager::class, [
        'ownerRecord' => $host,
        'pageClass' => EditProduct::class,
    ])
        ->callAction(TestAction::make(AttachAction::class)->table(), [
            'recordId' => $addition->id,
            'price' => 0,
            'quantity' => 1,
            'is_active' => true,
        ])
        ->assertHasErrors(['mountedActions.0.data.recordId']);

    expect($host->additions()->count())->toBe(1);
});

it('rejects an addition price delta that would resolve below zero', function () {
    $this->actingAs(User::factory()->admin()->create());

    $host = Product::factory()->create();
    $addition = Product::factory()->create(['price' => 20_000]);

    Livewire::test(AdditionsRelationManager::class, [
        'ownerRecord' => $host,
        'pageClass' => EditProduct::class,
    ])
        ->callAction(TestAction::make(AttachAction::class)->table(), [
            'recordId' => $addition->id,
            'price' => -20_001,
            'quantity' => 1,
            'is_active' => true,
        ])
        ->assertHasErrors(['mountedActions.0.data.price']);

    expect($host->additions()->count())->toBe(0);
});

it('allows an addition price delta that resolves to exactly zero', function () {
    $this->actingAs(User::factory()->admin()->create());

    $host = Product::factory()->create();
    $addition = Product::factory()->create(['price' => 20_000]);

    Livewire::test(AdditionsRelationManager::class, [
        'ownerRecord' => $host,
        'pageClass' => EditProduct::class,
    ])
        ->callAction(TestAction::make(AttachAction::class)->table(), [
            'recordId' => $addition->id,
            'price' => -20_000,
            'quantity' => 1,
            'is_active' => true,
        ])
        ->assertHasNoErrors();

    $attached = $host->additions()->first();

    expect($attached)->not->toBeNull()
        ->and($attached->pivot->price)->toBe(-20_000);
});
