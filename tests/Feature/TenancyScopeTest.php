<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('only shows customers belonging to the authenticated user\'s organisation', function () {
    $acme = Organisation::factory()->create();
    $globex = Organisation::factory()->create();

    $acmeUser = User::factory()->create(['organisation_id' => $acme->id]);
    Customer::factory()->create(['organisation_id' => $acme->id, 'name' => 'Acme Customer']);
    Customer::factory()->create(['organisation_id' => $globex->id, 'name' => 'Globex Customer']);

    Auth::login($acmeUser);

    expect(Customer::count())->toBe(1)
        ->and(Customer::first()->name)->toBe('Acme Customer');
});

it('only shows invoices belonging to the authenticated user\'s organisation', function () {
    $acme = Organisation::factory()->create();
    $globex = Organisation::factory()->create();

    $acmeUser = User::factory()->create(['organisation_id' => $acme->id]);
    $acmeCustomer = Customer::factory()->create(['organisation_id' => $acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);

    Invoice::factory()->create([
        'organisation_id' => $acme->id,
        'customer_id' => $acmeCustomer->id,
        'created_by_user_id' => $acmeUser->id,
        'number' => 'INV-0001',
    ]);
    Invoice::factory()->create([
        'organisation_id' => $globex->id,
        'customer_id' => $globexCustomer->id,
        'created_by_user_id' => User::factory()->create(['organisation_id' => $globex->id])->id,
        'number' => 'INV-0001',
    ]);

    Auth::login($acmeUser);

    expect(Invoice::count())->toBe(1)
        ->and(Invoice::first()->organisation_id)->toBe($acme->id);
});

it('cannot find a cross-tenant customer by id when scoped', function () {
    $acme = Organisation::factory()->create();
    $globex = Organisation::factory()->create();

    $acmeUser = User::factory()->create(['organisation_id' => $acme->id]);
    $globexCustomer = Customer::factory()->create(['organisation_id' => $globex->id]);

    Auth::login($acmeUser);

    expect(Customer::find($globexCustomer->id))->toBeNull();
});

it('sees all records when unauthenticated', function () {
    Organisation::factory()->count(2)->create()->each(
        fn (Organisation $organisation) => Customer::factory()->create(['organisation_id' => $organisation->id]),
    );

    expect(Customer::count())->toBe(2);
});
