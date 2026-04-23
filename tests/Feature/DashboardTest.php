<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('superadmin can visit the dashboard', function () {
    $user = User::factory()->superadmin()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('judge can visit the dashboard', function () {
    $user = User::factory()->judge()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});
