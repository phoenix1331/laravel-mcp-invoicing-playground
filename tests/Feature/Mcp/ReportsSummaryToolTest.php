<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\ReportsSummary;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('gets the dashboard summary for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    InvoicingServer::actingAs($user)->tool(ReportsSummary::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('drafts', 1)
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('only summarises the caller\'s organisation', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);
    Invoice::factory()->create([
        'organisation_id' => $this->globex->id,
        'customer_id' => $globexCustomer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    InvoicingServer::actingAs($acmeUser)->tool(ReportsSummary::class)
        ->assertStructuredContent(fn ($json) => $json
            ->where('drafts', 1)
            ->etc());
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(ReportsSummary::class)
        ->assertHasErrors(['Authentication is required']);
});
