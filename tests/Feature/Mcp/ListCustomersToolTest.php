<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\ListCustomers;
use App\Models\Customer;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
});

it('lists customers for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    Customer::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(ListCustomers::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('fails validation for an invalid page', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(ListCustomers::class, ['page' => 0])
        ->assertHasErrors();
});

it('only lists customers belonging to the caller\'s organisation', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Customer::factory()->create(['organisation_id' => $this->globex->id]);

    InvoicingServer::actingAs($acmeUser)->tool(ListCustomers::class)
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->etc());
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(ListCustomers::class)
        ->assertHasErrors(['Authentication is required']);
});
