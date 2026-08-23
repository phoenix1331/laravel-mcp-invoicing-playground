<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\CreateInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

function createInvoiceArguments(int $customerId): array
{
    return [
        'customer_id' => $customerId,
        'issue_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'currency' => 'GBP',
        'tax_rate' => 20,
        'lines' => [
            ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 150],
        ],
    ];
}

it('creates a draft invoice for owner and member', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);

    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, createInvoiceArguments($this->customer->id))
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('status', 'draft')
            ->where('total', 360)
            ->etc());
})->with([UserRole::Owner, UserRole::Member]);

it('denies a viewer from creating an invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);

    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, createInvoiceArguments($this->customer->id))
        ->assertHasErrors();
});

it('fails validation when lines are missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    $arguments = createInvoiceArguments($this->customer->id);
    unset($arguments['lines']);

    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, $arguments)
        ->assertHasErrors();
});

it('cannot create an invoice for a cross-tenant customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);

    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, createInvoiceArguments($globexCustomer->id))
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(CreateInvoice::class, createInvoiceArguments($this->customer->id))
        ->assertHasErrors(['Authentication is required']);
});

it('replays the same result instead of creating a second invoice when the idempotency key repeats', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    $arguments = [...createInvoiceArguments($this->customer->id), 'idempotency_key' => 'retry-abc-123'];

    $first = InvoicingServer::actingAs($user)->tool(CreateInvoice::class, $arguments);
    $first->assertOk();

    expect(Invoice::count())->toBe(1);

    $second = InvoicingServer::actingAs($user)->tool(CreateInvoice::class, $arguments);
    $second->assertOk();

    expect(Invoice::count())->toBe(1);

    $first->assertStructuredContent(fn ($json) => $json->where('id', Invoice::sole()->id)->etc());
    $second->assertStructuredContent(fn ($json) => $json->where('id', Invoice::sole()->id)->etc());
});

it('creates a second invoice when no idempotency key is given', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, createInvoiceArguments($this->customer->id))->assertOk();
    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, createInvoiceArguments($this->customer->id))->assertOk();

    expect(Invoice::count())->toBe(2);
});

it('creates a second invoice when the idempotency key differs', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, [...createInvoiceArguments($this->customer->id), 'idempotency_key' => 'key-one'])->assertOk();
    InvoicingServer::actingAs($user)->tool(CreateInvoice::class, [...createInvoiceArguments($this->customer->id), 'idempotency_key' => 'key-two'])->assertOk();

    expect(Invoice::count())->toBe(2);
});

it('does not replay a key across different organisations', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globex = Organisation::factory()->create();
    $globexUser = User::factory()->create(['organisation_id' => $globex->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);

    $key = 'shared-key';

    InvoicingServer::actingAs($acmeUser)->tool(CreateInvoice::class, [...createInvoiceArguments($this->customer->id), 'idempotency_key' => $key])->assertOk();
    InvoicingServer::actingAs($globexUser)->tool(CreateInvoice::class, [...createInvoiceArguments($globexCustomer->id), 'idempotency_key' => $key])->assertOk();

    expect(Invoice::withoutGlobalScopes()->where('organisation_id', $this->acme->id)->count())->toBe(1)
        ->and(Invoice::withoutGlobalScopes()->where('organisation_id', $globex->id)->count())->toBe(1);
});
