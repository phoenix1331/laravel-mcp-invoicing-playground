<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\MarkInvoicePaid;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('marks a sent invoice as paid for owner and member', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'paid')->etc());
})->with([UserRole::Owner, UserRole::Member]);

it('denies a viewer from marking an invoice paid', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors();
});

it('refuses to mark a draft invoice as paid', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['cannot transition']);
});

it('denies marking a cross-tenant invoice paid', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $globex->id, 'customer_id' => $globexCustomer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::tool(MarkInvoicePaid::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['Authentication is required']);
});
