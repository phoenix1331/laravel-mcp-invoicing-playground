<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;

it('redirects a guest away from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect('/login');
});

it('shows the dashboard with stat tiles and recent invoices', function () {
    $organisation = Organisation::factory()->create();
    $customer = Customer::factory()->create(['organisation_id' => $organisation->id]);
    $user = User::factory()->create(['organisation_id' => $organisation->id]);
    Invoice::factory()->create([
        'organisation_id' => $organisation->id,
        'customer_id' => $customer->id,
        'number' => 'INV-DASH-1',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Outstanding')
        ->assertSee('Overdue')
        ->assertSee('Paid this month')
        ->assertSee('Drafts')
        ->assertSee('INV-DASH-1');
});
