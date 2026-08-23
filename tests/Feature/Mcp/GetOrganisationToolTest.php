<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\GetOrganisation;
use App\Models\Organisation;
use App\Models\User;

it('gets the caller\'s organisation for every role', function (UserRole $role) {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => $role]);

    InvoicingServer::actingAs($user)->tool(GetOrganisation::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('id', $acme->id)
            ->where('name', $acme->name)
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('never returns another organisation\'s settings', function () {
    $acme = Organisation::factory()->create();
    $globex = Organisation::factory()->create();
    $acmeUser = User::factory()->create(['organisation_id' => $acme->id]);

    InvoicingServer::actingAs($acmeUser)->tool(GetOrganisation::class)
        ->assertStructuredContent(fn ($json) => $json
            ->where('id', $acme->id)
            ->etc());

    expect($acme->id)->not->toBe($globex->id);
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(GetOrganisation::class)
        ->assertHasErrors(['Authentication is required']);
});
