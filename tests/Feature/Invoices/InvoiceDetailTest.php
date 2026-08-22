<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
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

it('lets every role view an invoice detail page', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'number' => 'INV-0001',
    ]);

    $this->actingAs($user)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertSee('INV-0001');
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('returns a 404 for a cross-tenant invoice detail page', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->globex->id,
        'customer_id' => $globexCustomer->id,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.show', $invoice))
        ->assertNotFound();
});

it('lets a member send a draft invoice with lines', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);
    InvoiceLine::factory()->for($invoice)->create();

    $this->actingAs($user)
        ->post(route('invoices.send', $invoice))
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('shows an error when sending a draft invoice with no lines', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $this->actingAs($user)
        ->post(route('invoices.send', $invoice))
        ->assertRedirect(route('invoices.show', $invoice))
        ->assertSessionHasErrors('status');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('refuses to let a viewer send an invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);
    InvoiceLine::factory()->for($invoice)->create();

    $this->actingAs($user)
        ->post(route('invoices.send', $invoice))
        ->assertForbidden();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('lets a member mark a sent invoice as paid', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Sent,
    ]);

    $this->actingAs($user)
        ->post(route('invoices.mark-paid', $invoice))
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('lets a member void a sent invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Sent,
    ]);

    $this->actingAs($user)
        ->post(route('invoices.void', $invoice))
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

it('shows an error when marking a draft invoice as paid', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $this->actingAs($user)
        ->post(route('invoices.mark-paid', $invoice))
        ->assertRedirect(route('invoices.show', $invoice))
        ->assertSessionHasErrors('status');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('refuses to let a member transition a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->globex->id,
        'customer_id' => $globexCustomer->id,
        'status' => InvoiceStatus::Sent,
    ]);

    $this->actingAs($user)
        ->post(route('invoices.void', $invoice))
        ->assertNotFound();
});
