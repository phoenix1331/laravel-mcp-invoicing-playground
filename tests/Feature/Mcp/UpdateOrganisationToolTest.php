<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\UpdateOrganisation;
use App\Models\Organisation;
use App\Models\User;

it('updates the organisation for owner', function () {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($user)->tool(UpdateOrganisation::class, ['name' => 'Acme Renamed'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('name', 'Acme Renamed')->etc());
});

it('denies member and viewer from updating the organisation', function (UserRole $role) {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => $role]);

    InvoicingServer::actingAs($user)->tool(UpdateOrganisation::class, ['name' => 'Acme Renamed'])
        ->assertHasErrors();
})->with([UserRole::Member, UserRole::Viewer]);

it('fails validation when name is missing', function () {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($user)->tool(UpdateOrganisation::class, [])
        ->assertHasErrors();
});

it('never updates another organisation\'s settings', function () {
    $acme = Organisation::factory()->create(['name' => 'Acme']);
    $globex = Organisation::factory()->create(['name' => 'Globex']);
    $acmeUser = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($acmeUser)->tool(UpdateOrganisation::class, ['name' => 'Hijacked'])
        ->assertStructuredContent(fn ($json) => $json->where('id', $acme->id)->etc());

    expect(Organisation::find($globex->id)->name)->toBe('Globex');
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(UpdateOrganisation::class, ['name' => 'X'])
        ->assertHasErrors(['Authentication is required']);
});

it('replays the original result instead of updating again when the idempotency key repeats', function () {
    $acme = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $acme->id, 'role' => UserRole::Owner]);

    $arguments = ['name' => 'Acme Renamed', 'idempotency_key' => 'retry-org-update-1'];

    InvoicingServer::actingAs($user)->tool(UpdateOrganisation::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('name', 'Acme Renamed')->etc());

    InvoicingServer::actingAs($user)->tool(UpdateOrganisation::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('name', 'Acme Renamed')->etc());
});
