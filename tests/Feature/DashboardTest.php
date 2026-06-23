<?php

use App\Models\User;

test('guests visiting home are redirected to login', function () {
    $response = $this->get(route('home'));
    $response->assertRedirect(route('login'));
});

test('authenticated users visiting home are redirected to pos', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('home'));
    $response->assertRedirect(route('pos.index'));
});
