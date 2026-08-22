<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

it('registers a new user and assigns them to the default organisation as owner', function () {
    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => 'new-user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();

    $user = User::where('email', 'new-user@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Owner)
        ->and($user->organisation)->not->toBeNull();
});

it('rejects registration with a mismatched password confirmation', function () {
    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => 'mismatch@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
    expect(User::where('email', 'mismatch@example.com')->exists())->toBeFalse();
});

it('rejects registration with a duplicate email', function () {
    $existing = User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => $existing->email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
});
