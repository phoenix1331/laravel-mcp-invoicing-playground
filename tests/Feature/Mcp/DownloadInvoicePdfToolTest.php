<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\DownloadInvoicePdf;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('returns both a signed url and a resource link by default', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(DownloadInvoicePdf::class, ['invoice_id' => $invoice->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('invoice_id', $invoice->id)
            ->where('resource_uri', "invoice://{$invoice->id}/pdf")
            ->where('expires_in_minutes', 15)
            ->has('url')
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('returns only a url when format is url', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(DownloadInvoicePdf::class, ['invoice_id' => $invoice->id, 'format' => 'url'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('url')
            ->missing('resource_uri')
            ->etc());
});

it('returns only a resource link when format is blob', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);
    InvoiceLine::factory()->for($invoice)->create();

    InvoicingServer::actingAs($user)->tool(DownloadInvoicePdf::class, ['invoice_id' => $invoice->id, 'format' => 'blob'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('resource_uri')
            ->missing('url')
            ->etc());
});

it('fails validation for an invalid format', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);

    InvoicingServer::actingAs($user)->tool(DownloadInvoicePdf::class, ['invoice_id' => $invoice->id, 'format' => 'not-a-format'])
        ->assertHasErrors();
});

it('returns an error for an invoice that does not exist', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(DownloadInvoicePdf::class, ['invoice_id' => 999999])
        ->assertHasErrors(['No invoice was found']);
});

it('denies downloading a cross-tenant invoice pdf', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $globex->id, 'customer_id' => $globexCustomer->id]);

    InvoicingServer::actingAs($user)->tool(DownloadInvoicePdf::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $this->customer->id]);

    InvoicingServer::tool(DownloadInvoicePdf::class, ['invoice_id' => $invoice->id])
        ->assertHasErrors(['Authentication is required']);
});
