<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\UpdateInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

function updateInvoiceArguments(int $invoiceId, int $customerId): array
{
    return [
        'invoice_id' => $invoiceId,
        'customer_id' => $customerId,
        'issue_date' => '2026-02-01',
        'due_date' => '2026-02-28',
        'currency' => 'GBP',
        'tax_rate' => 10,
        'lines' => [
            ['description' => 'Design work', 'quantity' => 1, 'unit_price' => 200],
        ],
    ];
}

it('updates a draft invoice for owner and member', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(UpdateInvoice::class, updateInvoiceArguments($invoice->id, $this->customer->id))
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 220)
            ->etc());
})->with([UserRole::Owner, UserRole::Member]);

it('denies a viewer from updating an invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(UpdateInvoice::class, updateInvoiceArguments($invoice->id, $this->customer->id))
        ->assertHasErrors();
});

it('refuses to update a non-draft invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Sent]);

    InvoicingServer::actingAs($user)->tool(UpdateInvoice::class, updateInvoiceArguments($invoice->id, $this->customer->id))
        ->assertHasErrors(['can no longer be edited']);
});

it('fails validation when lines are missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    $arguments = updateInvoiceArguments($invoice->id, $this->customer->id);
    unset($arguments['lines']);

    InvoicingServer::actingAs($user)->tool(UpdateInvoice::class, $arguments)
        ->assertHasErrors();
});

it('denies updating a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $globex->id, 'customer_id' => $globexCustomer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::actingAs($user)->tool(UpdateInvoice::class, updateInvoiceArguments($invoice->id, $globexCustomer->id))
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id, 'status' => InvoiceStatus::Draft]);

    InvoicingServer::tool(UpdateInvoice::class, updateInvoiceArguments($invoice->id, $this->customer->id))
        ->assertHasErrors(['Authentication is required']);
});
