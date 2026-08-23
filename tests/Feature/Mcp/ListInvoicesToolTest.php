<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\ListInvoices;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('lists invoices for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);

    $response = InvoicingServer::actingAs($user)->tool(ListInvoices::class);

    $response->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('streams progress notifications while listing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);

    InvoicingServer::actingAs($user)->tool(ListInvoices::class)
        ->assertNotificationCount(2);
});

it('filters invoices by status', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Paid]);

    InvoicingServer::actingAs($user)->tool(ListInvoices::class, ['status' => 'paid'])
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->etc());
});

it('fails validation for an invalid status', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(ListInvoices::class, ['status' => 'not-a-status'])
        ->assertHasErrors();
});

it('only lists invoices belonging to the caller\'s organisation', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);
    Invoice::factory()->create(['organisation_id' => $this->globex->id, 'customer_id' => $globexCustomer->id]);

    InvoicingServer::actingAs($acmeUser)->tool(ListInvoices::class)
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->etc());
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(ListInvoices::class)
        ->assertHasErrors(['Authentication is required']);
});
