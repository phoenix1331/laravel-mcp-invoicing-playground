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

it('lets a member create a draft invoice with lines', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);

    $response = $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $this->customer->id,
        'issue_date' => '2026-01-01',
        'due_date' => '2026-01-31',
        'currency' => 'GBP',
        'tax_rate' => 20,
        'lines' => [
            ['description' => 'Design work', 'quantity' => 2, 'unit_price' => 100],
            ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 50],
        ],
    ]);

    $invoice = Invoice::first();
    $response->assertRedirect(route('invoices.edit', $invoice));

    expect($invoice->number)->toBe('INV-0001')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->organisation_id)->toBe($this->acme->id)
        ->and($invoice->created_by_user_id)->toBe($user->id)
        ->and($invoice->lines)->toHaveCount(2)
        ->and($invoice->subtotal)->toEqual(250)
        ->and($invoice->tax_total)->toEqual(50)
        ->and($invoice->total)->toEqual(300);
});

it('refuses to let a viewer create an invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);

    $this->actingAs($user)
        ->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'currency' => 'GBP',
            'tax_rate' => 20,
            'lines' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 100]],
        ])
        ->assertForbidden();

    expect(Invoice::count())->toBe(0);
});

it('rejects a store request with no lines', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);

    $this->actingAs($user)
        ->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'currency' => 'GBP',
            'tax_rate' => 20,
            'lines' => [],
        ])
        ->assertSessionHasErrors('lines');
});

it('lets a member update a draft invoice and replace its lines', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
        'customer_id' => $this->customer->id,
        'issue_date' => '2026-02-01',
        'due_date' => '2026-02-28',
        'currency' => 'GBP',
        'tax_rate' => 0,
        'lines' => [
            ['description' => 'Updated line', 'quantity' => 3, 'unit_price' => 10],
        ],
    ]);

    $response->assertRedirect(route('invoices.edit', $invoice));

    expect($invoice->fresh()->lines)->toHaveCount(1)
        ->and($invoice->fresh()->lines->first()->description)->toBe('Updated line')
        ->and($invoice->fresh()->total)->toEqual(30);
});

it('refuses to update a sent invoice through the editor', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $this->customer->id,
        'status' => InvoiceStatus::Sent,
    ]);

    $this->actingAs($user)
        ->put(route('invoices.update', $invoice), [
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'currency' => 'GBP',
            'tax_rate' => 20,
            'lines' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 100]],
        ])
        ->assertForbidden();
});

it('refuses to let a member update a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $this->globex->id]);
    $invoice = Invoice::factory()->create([
        'organisation_id' => $this->globex->id,
        'customer_id' => $globexCustomer->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $this->actingAs($user)
        ->put(route('invoices.update', $invoice), [
            'customer_id' => $globexCustomer->id,
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'currency' => 'GBP',
            'tax_rate' => 20,
            'lines' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 100]],
        ])
        ->assertNotFound();
});
