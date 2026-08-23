<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\AddInvoiceLine;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('adds a line to a draft invoice for owner and member', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(AddInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'description' => 'Extra work',
        'quantity' => 1,
        'unit_price' => 50,
    ])->assertOk()->assertStructuredContent(fn ($json) => $json
        ->where('invoice_id', $invoice->id)
        ->etc());

    expect($invoice->lines()->count())->toBe(2);
})->with([UserRole::Owner, UserRole::Member]);

it('denies a viewer from adding a line', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(AddInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'description' => 'Extra work',
        'quantity' => 1,
        'unit_price' => 50,
    ])->assertHasErrors();
});

it('refuses to add a line to a non-draft invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(AddInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'description' => 'Extra work',
        'quantity' => 1,
        'unit_price' => 50,
    ])->assertHasErrors(['can no longer be edited']);
});

it('fails validation when quantity is not numeric', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(AddInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'description' => 'Extra work',
        'quantity' => 'not-a-number',
        'unit_price' => 50,
    ])->assertHasErrors();
});

it('denies adding a line to a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $globex->id, 'customer_id' => $globexCustomer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(AddInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'description' => 'Extra work',
        'quantity' => 1,
        'unit_price' => 50,
    ])->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::tool(AddInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'description' => 'Extra work',
        'quantity' => 1,
        'unit_price' => 50,
    ])->assertHasErrors(['Authentication is required']);
});
