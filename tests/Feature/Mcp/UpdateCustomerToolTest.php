<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\UpdateCustomer;
use App\Models\Customer;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('updates a customer for owner and member', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);

    InvoicingServer::actingAs($user)->tool(UpdateCustomer::class, [
        'customer_id' => $this->customer->id,
        'name' => 'Renamed Co',
    ])->assertOk()->assertStructuredContent(fn ($json) => $json->where('name', 'Renamed Co')->etc());
})->with([UserRole::Owner, UserRole::Member]);

it('denies a viewer from updating a customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);

    InvoicingServer::actingAs($user)->tool(UpdateCustomer::class, [
        'customer_id' => $this->customer->id,
        'name' => 'Renamed Co',
    ])->assertHasErrors();
});

it('returns an error for a customer that does not exist', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(UpdateCustomer::class, ['customer_id' => 999999, 'name' => 'X'])
        ->assertHasErrors(['No customer was found']);
});

it('denies updating a cross-tenant customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);

    InvoicingServer::actingAs($user)->tool(UpdateCustomer::class, [
        'customer_id' => $globexCustomer->id,
        'name' => 'Hijacked',
    ])->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(UpdateCustomer::class, ['customer_id' => $this->customer->id, 'name' => 'X'])
        ->assertHasErrors(['Authentication is required']);
});
