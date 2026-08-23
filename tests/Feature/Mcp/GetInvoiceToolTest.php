<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\GetInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('gets an invoice with lines and customer for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(GetInvoice::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('id', $invoice->id)
            ->where('customer.id', $this->customer->id)
            ->has('lines', 1)
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('fails validation when invoice_id is missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(GetInvoice::class)
        ->assertHasErrors();
});

it('returns an error for an invoice that does not exist', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(GetInvoice::class, ['invoice_id' => 999999])
        ->assertHasErrors(['No invoice was found']);
});

it('denies a cross-tenant invoice lookup', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->globex->id, 'customer_id' => $globexCustomer->id]);

    InvoicingServer::actingAs($acmeUser)->tool(GetInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);

    InvoicingServer::tool(GetInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['Authentication is required']);
});
