<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\ListTeam;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
});

it('lists the caller\'s team for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);

    InvoicingServer::actingAs($user)->tool(ListTeam::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('members', 1)
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('only lists members of the caller\'s organisation', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    User::factory()->create(['organisation_id' => $this->globex->id]);

    InvoicingServer::actingAs($acmeUser)->tool(ListTeam::class)
        ->assertStructuredContent(fn ($json) => $json
            ->has('members', 1)
            ->etc());
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(ListTeam::class)
        ->assertHasErrors(['Authentication is required']);
});
