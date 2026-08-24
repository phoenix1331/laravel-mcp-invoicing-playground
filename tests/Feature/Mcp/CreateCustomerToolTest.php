<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Tools\CreateCustomer;
use App\Models\Customer;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
});

it('creates a customer for owner and member', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);

    InvoicingServer::actingAs($user)->tool(CreateCustomer::class, ['name' => 'New Co', 'email' => 'hello@newco.test'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('name', '<untrusted-data>New Co</untrusted-data>')->etc());

    expect(Customer::where('name', 'New Co')->where('organisation_id', $this->acme->id)->exists())->toBeTrue();
})->with([UserRole::Owner, UserRole::Member]);

it('never creates the customer in another organisation', function () {
    $globex = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);

    InvoicingServer::actingAs($user)->tool(CreateCustomer::class, ['name' => 'New Co'])
        ->assertOk();

    $customer = Customer::where('name', 'New Co')->sole();

    expect($customer->organisation_id)->toBe($this->acme->id)
        ->and($customer->organisation_id)->not->toBe($globex->id);
});

it('denies a viewer from creating a customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);

    InvoicingServer::actingAs($user)->tool(CreateCustomer::class, ['name' => 'New Co'])
        ->assertHasErrors();
});

it('fails validation when name is missing', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    InvoicingServer::actingAs($user)->tool(CreateCustomer::class, [])
        ->assertHasErrors();
});

it('denies an unauthenticated caller', function () {
    InvoicingServer::tool(CreateCustomer::class, ['name' => 'New Co'])
        ->assertHasErrors(['Authentication is required']);
});

it('replays the same result instead of creating a second customer when the idempotency key repeats', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);

    $arguments = ['name' => 'New Co', 'email' => 'hello@newco.test', 'idempotency_key' => 'retry-customer-1'];

    InvoicingServer::actingAs($user)->tool(CreateCustomer::class, $arguments)->assertOk();

    expect(Customer::where('name', 'New Co')->count())->toBe(1);

    InvoicingServer::actingAs($user)->tool(CreateCustomer::class, $arguments)->assertOk();

    expect(Customer::where('name', 'New Co')->count())->toBe(1);
});
