<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Resources\InvoiceResource;
use App\Mcp\Servers\InvoicingServer;
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

it('reads an invoice as markdown for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'number' => 'INV-0001',
    ]);
    InvoiceLine::factory()->for($invoice)->create(['description' => 'Consulting']);

    InvoicingServer::actingAs($user)->resource(InvoiceResource::class, ['invoiceId' => (string) $invoice->id])
        ->assertOk()
        ->assertSee(['INV-0001', 'Consulting']);
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('returns an error for an invoice that does not exist', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->resource(InvoiceResource::class, ['invoiceId' => '999999'])
        ->assertHasErrors(['No invoice was found']);
});

it('denies a cross-tenant invoice read', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->globex->id, 'customer_id' => $globexCustomer->id]);

    InvoicingServer::actingAs($acmeUser)->resource(InvoiceResource::class, ['invoiceId' => (string) $invoice->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);

    InvoicingServer::resource(InvoiceResource::class, ['invoiceId' => (string) $invoice->id])
        ->assertHasErrors(['Authentication is required']);
});
