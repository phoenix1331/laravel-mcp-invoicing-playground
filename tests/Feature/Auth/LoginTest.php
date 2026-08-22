<?php

declare(strict_types=1);

use App\Models\User;

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('rejects login with an incorrect password', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs out an authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/');
    $this->assertGuest();
});
