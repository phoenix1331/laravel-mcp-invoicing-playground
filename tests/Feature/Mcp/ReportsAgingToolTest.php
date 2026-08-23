<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\ReportsAging;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('buckets sent invoices by how overdue they are for every role', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => Carbon::today()->subDays(45),
        'total' => 100,
    ]);

    InvoicingServer::actingAs($user)->tool(ReportsAging::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('buckets.31_60', 100)
            ->where('total_outstanding', 100)
            ->etc());
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('excludes draft and paid invoices from the aging report', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
        'due_date' => Carbon::today()->subDays(45),
        'total' => 100,
    ]);
    Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Paid,
        'due_date' => Carbon::today()->subDays(45),
        'total' => 100,
    ]);

    InvoicingServer::actingAs($user)->tool(ReportsAging::class)
        ->assertStructuredContent(fn ($json) => $json
            ->where('total_outstanding', 0)
            ->etc());
});

it('only ages the caller\'s organisation invoices', function () {
    $acmeUser = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    Invoice::factory()->create([
        'organisation_id' => $this->globex->id,
        'customer_id' => $globexCustomer->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => Carbon::today()->subDays(100),
        'total' => 500,
    ]);

    InvoicingServer::actingAs($acmeUser)->tool(ReportsAging::class)
        ->assertStructuredContent(fn ($json) => $json
            ->where('total_outstanding', 0)
            ->etc());
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(ReportsAging::class)
        ->assertHasErrors(['Authentication is required']);
});
