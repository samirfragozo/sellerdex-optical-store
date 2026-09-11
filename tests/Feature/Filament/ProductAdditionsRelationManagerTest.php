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
