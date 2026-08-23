<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\VoidInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('voids a sent invoice for owner and member when confirmed', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, ['invoice_id' => $invoice->id, 'confirm' => true])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'void')->etc());
})->with([UserRole::Owner, UserRole::Member]);

it('returns a structured confirmation request instead of voiding when confirm is absent', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('requires_confirmation', true)
            ->etc());

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('fails validation when invoice_id is missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, ['confirm' => true])
        ->assertHasErrors();
});

it('denies a viewer from voiding an invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, ['invoice_id' => $invoice->id, 'confirm' => true])
        ->assertHasErrors();
});

it('refuses to void a draft invoice when confirmed', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, ['invoice_id' => $invoice->id, 'confirm' => true])
        ->assertHasErrors(['cannot transition']);
});

it('denies voiding a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $globex->id, 'customer_id' => $globexCustomer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, ['invoice_id' => $invoice->id, 'confirm' => true])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::tool(VoidInvoice::class, ['invoice_id' => $invoice->id, 'confirm' => true])
        ->assertHasErrors(['Authentication is required']);
});

it('replays the original result instead of failing on retry after the invoice was already voided', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    $arguments = ['invoice_id' => $invoice->id, 'confirm' => true, 'idempotency_key' => 'retry-void-1'];

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'void')->etc());

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'void')->etc());
});

it('does not cache an unconfirmed request under the idempotency key', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    $key = 'confirm-then-retry';

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, ['invoice_id' => $invoice->id, 'idempotency_key' => $key])
        ->assertStructuredContent(fn ($json) => $json->where('requires_confirmation', true)->etc());

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);

    InvoicingServer::actingAs($user)->tool(VoidInvoice::class, ['invoice_id' => $invoice->id, 'confirm' => true, 'idempotency_key' => $key])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'void')->etc());

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});
