<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\DeleteInvoiceTool;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('deletes a draft invoice outright for owner', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(DeleteInvoiceTool::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'deleted')->etc());

    expect(Invoice::withoutGlobalScopes()->find($invoice->id))->toBeNull();
});

it('voids a non-draft invoice instead of deleting it', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(DeleteInvoiceTool::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'void')->etc());
});

it('denies member and viewer from deleting an invoice', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(DeleteInvoiceTool::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors();
})->with([UserRole::Member, UserRole::Viewer]);

it('fails validation when invoice_id is missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($user)->tool(DeleteInvoiceTool::class, [])
        ->assertHasErrors();
});

it('denies deleting a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $globex->id, 'customer_id' => $globexCustomer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(DeleteInvoiceTool::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::tool(DeleteInvoiceTool::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['Authentication is required']);
});

it('replays the original result on retry after the invoice was already deleted', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    $arguments = ['invoice_id' => $invoice->id, 'idempotency_key' => 'retry-delete-1'];

    InvoicingServer::actingAs($user)->tool(DeleteInvoiceTool::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'deleted')->etc());

    InvoicingServer::actingAs($user)->tool(DeleteInvoiceTool::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'deleted')->etc());
});
