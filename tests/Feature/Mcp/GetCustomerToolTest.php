<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\GetCustomer;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
});

it('gets a customer with invoice history for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id]);

    InvoicingServer::actingAs($user)->tool(GetCustomer::class, ['customer_id' => $customer->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('id', $customer->id)
            ->has('invoices', 1)
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('fails validation when customer_id is missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(GetCustomer::class)
        ->assertHasErrors();
});

it('returns an error for a customer that does not exist', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(GetCustomer::class, ['customer_id' => 999999])
        ->assertHasErrors(['No customer was found']);
});

it('denies a cross-tenant customer lookup', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    InvoicingServer::actingAs($acmeUser)->tool(GetCustomer::class, ['customer_id' => $globexCustomer->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::tool(GetCustomer::class, ['customer_id' => $customer->id])
        ->assertHasErrors(['Authentication is required']);
});
