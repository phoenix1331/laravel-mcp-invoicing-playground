<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Organisation;
use App\Models\User;

it('redirects a guest away from the team page', function () {
    $this->get(route('settings.team'))->assertRedirect('/login');
});

it('shows the team list to the owner', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner, 'name' => 'Ada Owner']);
    User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Member, 'name' => 'Bob Member']);

    $this->actingAs($owner)
        ->get(route('settings.team'))
        ->assertOk()
        ->assertSee(['Ada Owner', 'Bob Member']);
});

it('denies member and viewer from viewing the team page', function (UserRole $role) {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => $role]);

    $this->actingAs($user)
        ->get(route('settings.team'))
        ->assertForbidden();
})->with([UserRole::Member, UserRole::Viewer]);

it('lets the owner invite a new member and shows the temporary password once', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);

    $response = $this->actingAs($owner)->post(route('settings.team.store'), [
        'name' => 'New Member',
        'email' => 'new-member@example.com',
        'role' => UserRole::Member->value,
    ]);

    $response->assertRedirect(route('settings.team'));
    $response->assertSessionHas('temporaryPassword');

    $member = User::where('email', 'new-member@example.com')->first();

    expect($member)->not->toBeNull()
        ->and($member->organisation_id)->toBe($organisation->id)
        ->and($member->role)->toBe(UserRole::Member);
});

it('denies member and viewer from inviting a new member', function (UserRole $role) {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => $role]);

    $this->actingAs($user)
        ->post(route('settings.team.store'), [
            'name' => 'New Member',
            'email' => 'new-member@example.com',
            'role' => UserRole::Member->value,
        ])
        ->assertForbidden();

    expect(User::where('email', 'new-member@example.com')->exists())->toBeFalse();
})->with([UserRole::Member, UserRole::Viewer]);

it('fails validation when the invited email is already taken', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);
    $existing = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($owner)
        ->post(route('settings.team.store'), [
            'name' => 'New Member',
            'email' => $existing->email,
            'role' => UserRole::Member->value,
        ])
        ->assertSessionHasErrors('email');
});

it('lets the owner change a team member role', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Member]);

    $this->actingAs($owner)
        ->put(route('settings.team.update', $member), ['role' => UserRole::Viewer->value])
        ->assertRedirect(route('settings.team'));

    expect($member->fresh()->role)->toBe(UserRole::Viewer);
});

it('denies member and viewer from changing a team member role', function (UserRole $role) {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'role' => $role]);
    $member = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Member]);

    $this->actingAs($user)
        ->put(route('settings.team.update', $member), ['role' => UserRole::Viewer->value])
        ->assertForbidden();

    expect($member->fresh()->role)->toBe(UserRole::Member);
})->with([UserRole::Member, UserRole::Viewer]);

it('denies an owner from changing a cross-tenant member role', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);

    $otherOrganisation = Organisation::factory()->create();
    $otherMember = User::factory()->create(['organisation_id' => $otherOrganisation->id, 'role' => UserRole::Member]);

    $this->actingAs($owner)
        ->put(route('settings.team.update', $otherMember), ['role' => UserRole::Viewer->value])
        ->assertForbidden();

    expect($otherMember->fresh()->role)->toBe(UserRole::Member);
});

it('fails validation when the role is invalid', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $organisation->id, 'role' => UserRole::Member]);

    $this->actingAs($owner)
        ->put(route('settings.team.update', $member), ['role' => 'not-a-role'])
        ->assertSessionHasErrors('role');
});
