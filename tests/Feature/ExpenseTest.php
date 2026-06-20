<?php

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('registra actividad al crear un gasto', function () {
    $expense = Expense::factory()->create();

    expect(Activity::where('subject_type', Expense::class)
        ->where('subject_id', $expense->id)->exists())->toBeTrue();
});

it('los gastos son exclusivos del admin', function () {
    $seller = User::factory()->seller()->create();
    $admin = User::factory()->admin()->create();

    expect($seller->can('ViewAny:Expense'))->toBeFalse()
        ->and($admin->can('ViewAny:Expense'))->toBeTrue()
        ->and($admin->can('Delete:Expense'))->toBeTrue();
});
