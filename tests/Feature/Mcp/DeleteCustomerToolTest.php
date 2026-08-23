<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\DeleteCustomer;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
});

it('deletes a customer with no invoices for owner', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(DeleteCustomer::class, ['customer_id' => $customer->id])
        ->assertOk();

    expect(Customer::withoutGlobalScopes()->find($customer->id))->toBeNull();
});

it('denies member and viewer from deleting a customer', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(DeleteCustomer::class, ['customer_id' => $customer->id])
        ->assertHasErrors();
})->with([UserRole::Member, UserRole::Viewer]);

it('refuses to delete a customer that has invoices', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id]);

    InvoicingServer::actingAs($user)->tool(DeleteCustomer::class, ['customer_id' => $customer->id])
        ->assertHasErrors();
});

it('returns an error for a customer that does not exist', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($user)->tool(DeleteCustomer::class, ['customer_id' => 999999])
        ->assertHasErrors(['No customer was found']);
});

it('denies deleting a cross-tenant customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);

    InvoicingServer::actingAs($user)->tool(DeleteCustomer::class, ['customer_id' => $globexCustomer->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::tool(DeleteCustomer::class, ['customer_id' => $customer->id])
        ->assertHasErrors(['Authentication is required']);
});

it('replays the original result on retry after the customer was already deleted', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    $arguments = ['customer_id' => $customer->id, 'idempotency_key' => 'retry-delete-1'];

    InvoicingServer::actingAs($user)->tool(DeleteCustomer::class, $arguments)->assertOk();
    InvoicingServer::actingAs($user)->tool(DeleteCustomer::class, $arguments)->assertOk();
});
