<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
});

it('lets an owner delete a draft invoice outright', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $invoice))
        ->assertRedirect(route('invoices.index'));

    expect(Invoice::find($invoice->id))->toBeNull();
});

it('voids a sent invoice instead of deleting it', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Sent,
    ]);

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $invoice))
        ->assertRedirect(route('invoices.index'));

    expect(Invoice::find($invoice->id))->not->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

it('refuses to let a member delete an invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $invoice))
        ->assertForbidden();

    expect(Invoice::find($invoice->id))->not->toBeNull();
});

it('refuses to let a viewer delete an invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $invoice))
        ->assertForbidden();
});

it('refuses to let an owner delete a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->globex->id,
        'customer_id' => $globexCustomer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $invoice))
        ->assertNotFound();

    expect(Invoice::withoutGlobalScopes()->find($invoice->id))->not->toBeNull();
});

it('redirects a guest attempting to delete an invoice', function () {
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->delete(route('invoices.destroy', $invoice))->assertRedirect('/login');
});
