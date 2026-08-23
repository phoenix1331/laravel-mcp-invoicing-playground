<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\SetTeamMemberRole;
use App\Models\Organisation;
use App\Models\User;

it('changes a team member\'s role for owner', function () {
    $acme = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Member]);

    InvoicingServer::actingAs($owner)->tool(SetTeamMemberRole::class, [
        'user_id' => $member->id,
        'role' => 'viewer',
    ])->assertOk()->assertStructuredContent(fn ($json) => $json->where('role', 'viewer')->etc());

    expect($member->fresh()->role)->toBe(UserRole::Viewer);
});

it('denies member and viewer from changing a role', function (UserRole $role) {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => $role]);
    $member = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Member]);

    InvoicingServer::actingAs($user)->tool(SetTeamMemberRole::class, [
        'user_id' => $member->id,
        'role' => 'viewer',
    ])->assertHasErrors();
})->with([UserRole::Member, UserRole::Viewer]);

it('fails validation for an invalid role', function () {
    $acme = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Member]);

    InvoicingServer::actingAs($owner)->tool(SetTeamMemberRole::class, [
        'user_id' => $member->id,
        'role' => 'not-a-role',
    ])->assertHasErrors();
});

it('returns an error for a team member that does not exist', function () {
    $acme = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($owner)->tool(SetTeamMemberRole::class, ['user_id' => 999999, 'role' => 'viewer'])
        ->assertHasErrors(['No team member was found']);
});

it('denies changing the role of a cross-tenant user', function () {
    $acme = Organisation::factory()->create();
    $globex = Organisation::factory()->create();
    $acmeOwner = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);
    $globexMember = User::factory()->create(['organisation_id' => $globex->id, 'role' => UserRole::Member]);

    InvoicingServer::actingAs($acmeOwner)->tool(SetTeamMemberRole::class, [
        'user_id' => $globexMember->id,
        'role' => 'owner',
    ])->assertHasErrors();

    expect($globexMember->fresh()->role)->toBe(UserRole::Member);
});

it('denies an unauthenticated caller', function () {
    $acme = Organisation::factory()->create();
    $member = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Member]);

    InvoicingServer::tool(SetTeamMemberRole::class, ['user_id' => $member->id, 'role' => 'viewer'])
        ->assertHasErrors(['Authentication is required']);
});

it('replays the original result instead of changing the role again when the idempotency key repeats', function () {
    $acme = Organisation::factory()->create();
    $owner = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Member]);

    $arguments = ['user_id' => $member->id, 'role' => 'viewer', 'idempotency_key' => 'retry-set-role-1'];

    InvoicingServer::actingAs($owner)->tool(SetTeamMemberRole::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('role', 'viewer')->etc());

    InvoicingServer::actingAs($owner)->tool(SetTeamMemberRole::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('role', 'viewer')->etc());
});
