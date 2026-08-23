<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('redirects a guest away from the tokens page', function () {
    $this->get(route('settings.tokens'))->assertRedirect('/login');
});

it('lists the caller\'s tokens', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);
    $user->createToken('Claude Desktop');

    $this->actingAs($user)
        ->get(route('settings.tokens'))
        ->assertOk()
        ->assertSee('Claude Desktop');
});

it('creates a token and shows the plain text value once', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $response = $this->actingAs($user)
        ->post(route('settings.tokens.store'), ['name' => 'Claude Desktop']);

    $response->assertRedirect(route('settings.tokens'));
    $response->assertSessionHas('plainTextToken');

    expect($user->tokens()->where('name', 'Claude Desktop')->exists())->toBeTrue();
});

it('offers a copy button alongside the newly created plain text token', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('settings.tokens.store'), ['name' => 'Claude Desktop'])
        ->assertOk()
        ->assertSee('Copy')
        ->assertSee('Copied!');
});

it('fails validation when the token name is missing', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($user)
        ->post(route('settings.tokens.store'), [])
        ->assertSessionHasErrors('name');
});

it('revokes the caller\'s own token', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);
    $token = $user->createToken('Claude Desktop')->accessToken;

    $this->actingAs($user)
        ->delete(route('settings.tokens.destroy', $token))
        ->assertRedirect(route('settings.tokens'));

    expect(PersonalAccessToken::find($token->id))->toBeNull();
});

it('does not allow revoking another user\'s token', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $organisation->id]);
    $otherUser = User::factory()->create(['organisation_id' => $organisation->id]);
    $token = $owner->createToken('Claude Desktop')->accessToken;

    $this->actingAs($otherUser)
        ->delete(route('settings.tokens.destroy', $token))
        ->assertNotFound();

    expect(PersonalAccessToken::find($token->id))->not->toBeNull();
});
