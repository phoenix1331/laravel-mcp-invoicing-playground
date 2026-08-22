<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
});

it('redirects a guest away from the customer list', function () {
    $this->get(route('customers.index'))->assertRedirect('/login');
});

it('lets every role view the customer list', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    Customer::factory()->create(['organisation_id' => $this->acme->id, 'name' => 'Acme Customer']);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Acme Customer');
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('does not show a cross-tenant customer in the list', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    Customer::factory()->create(['organisation_id' => $this->globex->id, 'name' => 'Globex Customer']);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertDontSee('Globex Customer');
});

it('lets a member create a customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);

    $response = $this->actingAs($user)->post(route('customers.store'), [
        'name' => 'New Customer',
        'email' => 'new@example.com',
    ]);

    $customer = Customer::first();
    $response->assertRedirect(route('customers.show', $customer));
    expect($customer->name)->toBe('New Customer')
        ->and($customer->organisation_id)->toBe($this->acme->id);
});

it('refuses to let a viewer create a customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);

    $this->actingAs($user)
        ->post(route('customers.store'), ['name' => 'New Customer'])
        ->assertForbidden();

    expect(Customer::count())->toBe(0);
});

it('lets a member update a customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), ['name' => 'Updated Name'])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->fresh()->name)->toBe('Updated Name');
});

it('refuses to let a member update a cross-tenant customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $customer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), ['name' => 'Updated Name'])
        ->assertNotFound();
});

it('lets an owner delete a customer with no invoices', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    expect(Customer::find($customer->id))->toBeNull();
});

it('refuses to let a member delete a customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();

    expect(Customer::find($customer->id))->not->toBeNull();
});

it('refuses to let an owner delete a customer that has invoices', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();

    expect(Customer::find($customer->id))->not->toBeNull();
});

it('shows a customer with their invoices', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create([
        'organisation_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'number' => 'INV-0001',
    ]);

    $this->actingAs($user)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee('INV-0001');
});

it('returns a 404 for a cross-tenant customer detail page', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $customer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    $this->actingAs($user)
        ->get(route('customers.show', $customer))
        ->assertNotFound();
});
