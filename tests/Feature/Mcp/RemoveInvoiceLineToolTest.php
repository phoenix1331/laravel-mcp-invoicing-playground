<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\RemoveInvoiceLine;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('removes a line from a draft invoice for owner and member', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    InvoiceLine::factory()->for($invoice)->create();
    $line = InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(RemoveInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'line_id' => $line->id,
    ])->assertOk();

    expect($invoice->lines()->count())->toBe(1);
})->with([UserRole::Owner, UserRole::Member]);

it('denies a viewer from removing a line', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    $line = InvoiceLine::factory()->for($invoice)->create();
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(RemoveInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'line_id' => $line->id,
    ])->assertHasErrors();
});

it('refuses to remove the last remaining line', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    $line = InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(RemoveInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'line_id' => $line->id,
    ])->assertHasErrors(['must have at least one line']);
});

it('fails validation when line_id is missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(RemoveInvoiceLine::class, [
        'invoice_id' => $invoice->id,
    ])->assertHasErrors();
});

it('denies removing a line from a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $globex->id, 'customer_id' => $globexCustomer->id, 'status' => InvoiceStatus::Draft]);
    $line = InvoiceLine::factory()->for($invoice)->create();
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(RemoveInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'line_id' => $line->id,
    ])->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    $line = InvoiceLine::factory()->for($invoice)->create();
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::tool(RemoveInvoiceLine::class, [
        'invoice_id' => $invoice->id,
        'line_id' => $line->id,
    ])->assertHasErrors(['Authentication is required']);
});
