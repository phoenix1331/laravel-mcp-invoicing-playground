<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\SendInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('sends a draft invoice for owner and member', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(SendInvoice::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'sent')->etc());
})->with([UserRole::Owner, UserRole::Member]);

it('denies a viewer from sending an invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(SendInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors();
});

it('fails validation when invoice_id is missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(SendInvoice::class, [])
        ->assertHasErrors();
});

it('refuses to send an invoice with no lines', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(SendInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['at least one line']);
});

it('refuses to send an already-sent invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(SendInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['cannot transition']);
});

it('denies sending a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $globex->id, 'customer_id' => $globexCustomer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(SendInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::tool(SendInvoice::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['Authentication is required']);
});

it('replays the original result instead of failing on retry after the invoice was already sent', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    InvoiceLine::factory()->for($invoice)->create();

    $arguments = ['invoice_id' => $invoice->id, 'idempotency_key' => 'retry-send-1'];

    InvoicingServer::actingAs($user)->tool(SendInvoice::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'sent')->etc());

    InvoicingServer::actingAs($user)->tool(SendInvoice::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'sent')->etc());
});
