<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\InviteTeamMember;
use App\Models\Organisation;
use App\Models\User;

it('invites a new team member for owner', function () {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($user)->tool(InviteTeamMember::class, [
        'name' => 'New Member',
        'email' => 'new.member@acme.test',
        'role' => 'member',
    ])->assertOk()->assertStructuredContent(fn ($json) => $json
        ->where('email', '<untrusted-data>new.member@acme.test</untrusted-data>')
        ->has('temporary_password')
        ->etc());

    expect(User::where('email', 'new.member@acme.test')->where('organisation_id', $acme->id)->exists())->toBeTrue();
});

it('denies member and viewer from inviting a team member', function (UserRole $role) {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => $role]);

    InvoicingServer::actingAs($user)->tool(InviteTeamMember::class, [
        'name' => 'New Member',
        'email' => 'new.member@acme.test',
        'role' => 'member',
    ])->assertHasErrors();
})->with([UserRole::Member, UserRole::Viewer]);

it('fails validation when the email is already taken', function () {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);
    $existing = User::factory()->create(['organisation_id' => $acme->id]);

    InvoicingServer::actingAs($user)->tool(InviteTeamMember::class, [
        'name' => 'New Member',
        'email' => $existing->email,
        'role' => 'member',
    ])->assertHasErrors();
});

it('fails validation for an invalid role', function () {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($user)->tool(InviteTeamMember::class, [
        'name' => 'New Member',
        'email' => 'new.member@acme.test',
        'role' => 'not-a-role',
    ])->assertHasErrors();
});

it('adds the new member to the caller\'s organisation, not another', function () {
    $acme = Organisation::factory()->create();
    $globex = Organisation::factory()->create();
    $acmeOwner = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($acmeOwner)->tool(InviteTeamMember::class, [
        'name' => 'New Member',
        'email' => 'new.member@acme.test',
        'role' => 'member',
    ])->assertOk();

    $member = User::where('email', 'new.member@acme.test')->firstOrFail();

    expect($member->organisation_id)->toBe($acme->id)
        ->and($member->organisation_id)->not->toBe($globex->id);
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(InviteTeamMember::class, [
        'name' => 'New Member',
        'email' => 'new.member@acme.test',
        'role' => 'member',
    ])->assertHasErrors(['Authentication is required']);
});

it('replays the same result instead of inviting a second member when the idempotency key repeats', function () {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    $arguments = [
        'name' => 'New Member',
        'email' => 'new.member@acme.test',
        'role' => 'member',
        'idempotency_key' => 'retry-invite-1',
    ];

    InvoicingServer::actingAs($user)->tool(InviteTeamMember::class, $arguments)->assertOk();

    expect(User::where('email', 'new.member@acme.test')->count())->toBe(1);

    InvoicingServer::actingAs($user)->tool(InviteTeamMember::class, $arguments)->assertOk();

    expect(User::where('email', 'new.member@acme.test')->count())->toBe(1);
});
