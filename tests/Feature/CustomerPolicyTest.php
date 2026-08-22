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

it('lets every role view customers', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    expect($user->can('view', $customer))->toBeTrue();
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('refuses to let a user view a cross-tenant customer', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $customer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    expect($user->can('view', $customer))->toBeFalse();
});

it('lets owners and members create and update customers, but not viewers', function () {
    $owner = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $viewer = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    expect($owner->can('create', Customer::class))->toBeTrue()
        ->and($member->can('create', Customer::class))->toBeTrue()
        ->and($viewer->can('create', Customer::class))->toBeFalse()
        ->and($owner->can('update', $customer))->toBeTrue()
        ->and($member->can('update', $customer))->toBeTrue()
        ->and($viewer->can('update', $customer))->toBeFalse();
});

it('only lets an owner delete a customer with no invoices', function () {
    $owner = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);

    expect($owner->can('delete', $customer))->toBeTrue()
        ->and($member->can('delete', $customer))->toBeFalse();
});

it('refuses to delete a customer that has invoices', function () {
    $owner = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $customer = Customer::factory()->create(['organisation_id' => $this->acme->id]);
    Invoice::factory()->create(['organisation_id' => $this->acme->id, 'customer_id' => $customer->id]);

    expect($owner->can('delete', $customer))->toBeFalse();
});

it('refuses to let an owner delete a cross-tenant customer', function () {
    $owner = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $customer = Customer::factory()->create(['organisation_id' => $this->globex->id]);

    expect($owner->can('delete', $customer))->toBeFalse();
});
