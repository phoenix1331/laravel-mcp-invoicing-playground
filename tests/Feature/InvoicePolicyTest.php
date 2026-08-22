<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->acme = Organisation::factory()->create();
    $this->globex = Organisation::factory()->create();
});

it('lets every role view an invoice in their own organisation', function (UserRole $role) {
    $user = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => $role]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id]);

    expect($user->can('view', $invoice))->toBeTrue();
})->with([UserRole::Owner, UserRole::Member, UserRole::Viewer]);

it('refuses to let a user view a cross-tenant invoice', function () {
    $user = User::factory()->create(['organisation_id' => $this->acme->id]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->globex->id]);

    expect($user->can('view', $invoice))->toBeFalse();
});

it('lets owners and members create and update invoices, but not viewers', function () {
    $owner = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $viewer = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Viewer]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id]);

    expect($owner->can('create', Invoice::class))->toBeTrue()
        ->and($member->can('create', Invoice::class))->toBeTrue()
        ->and($viewer->can('create', Invoice::class))->toBeFalse()
        ->and($owner->can('update', $invoice))->toBeTrue()
        ->and($member->can('update', $invoice))->toBeTrue()
        ->and($viewer->can('update', $invoice))->toBeFalse();
});

it('only lets an owner delete an invoice', function () {
    $owner = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $member = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->acme->id]);

    expect($owner->can('delete', $invoice))->toBeTrue()
        ->and($member->can('delete', $invoice))->toBeFalse();
});

it('refuses to let an owner delete a cross-tenant invoice', function () {
    $owner = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Owner]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->globex->id]);

    expect($owner->can('delete', $invoice))->toBeFalse();
});

it('refuses to let a member update a cross-tenant invoice', function () {
    $member = User::factory()->create(['organisation_id' => $this->acme->id, 'role' => UserRole::Member]);
    $invoice = Invoice::factory()->create(['organisation_id' => $this->globex->id]);

    expect($member->can('update', $invoice))->toBeFalse();
});
