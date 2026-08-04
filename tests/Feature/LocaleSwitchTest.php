<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('switches the locale and persists it in the session and cookie', function () {
    $this->post('/locale/en')
        ->assertRedirect()
        ->assertSessionHas('locale', 'en')
        ->assertCookie('filament_language_switch_locale', 'en');
});

it('applies the session locale to the following request', function () {
    $this->withSession(['locale' => 'en'])
        ->actingAs(User::factory()->seller()->create())
        ->get('/pos')
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('rejects an unsupported locale', function () {
    $this->post('/locale/fr')->assertNotFound();
});

it('lets a guest switch locale from the login page', function () {
    $this->post('/locale/en')->assertRedirect();
});
