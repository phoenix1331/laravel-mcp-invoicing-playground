<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Resources\CustomerResource;
use App\Mcp\Servers\InvoicingServer;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
});

it('reads a customer with invoice history as markdown for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id, 'name' => 'Acme Trading Co']);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id, 'number' => 'INV-0001']);

    InvoicingServer::actingAs($user)->resource(CustomerResource::class, ['customerId' => (string) $customer->id])
        ->assertOk()
        ->assertSee(['Acme Trading Co', 'INV-0001']);
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('shows a message when the customer has no invoices', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->resource(CustomerResource::class, ['customerId' => (string) $customer->id])
        ->assertSee('No invoices yet');
});

it('returns an error for a customer that does not exist', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->resource(CustomerResource::class, ['customerId' => '999999'])
        ->assertHasErrors(['No customer was found']);
});

it('denies a cross-tenant customer read', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    InvoicingServer::actingAs($acmeUser)->resource(CustomerResource::class, ['customerId' => (string) $globexCustomer->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::resource(CustomerResource::class, ['customerId' => (string) $customer->id])
        ->assertHasErrors(['Authentication is required']);
});
