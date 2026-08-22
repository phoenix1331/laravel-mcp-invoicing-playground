<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

it('redirects a two-factor-enabled user to the challenge screen on login', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect('/two-factor-challenge');
    $this->assertGuest();
});

it('logs in with a valid recovery code after the challenge', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $recoveryCode = $user->fresh()->recoveryCodes()[0];

    $response = $this->post('/two-factor-challenge', [
        'recovery_code' => $recoveryCode,
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user->fresh());
});

it('rejects an invalid two-factor code at the challenge', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response = $this->post('/two-factor-challenge', [
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});
