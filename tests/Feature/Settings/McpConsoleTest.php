<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;

it('redirects a guest away from the mcp console', function () {
    $this->get(route('settings.mcp'))->assertRedirect('/login');
});

it('shows the live tool catalogue', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($user)
        ->get(route('settings.mcp'))
        ->assertOk()
        ->assertSee([
            'invoices.list',
            'invoices.get',
            'customers.list',
            'customers.get',
            'organisation.get',
            'team.list',
            'reports.summary',
            'reports.aging',
        ]);
});

it('shows the live resource catalogue', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($user)
        ->get(route('settings.mcp'))
        ->assertOk()
        ->assertSee([
            'invoicing://guidelines',
            'invoicing://schema',
            'invoice://{invoiceId}',
            'customer://{customerId}',
        ]);
});

it('shows connection details for both transports', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($user)
        ->get(route('settings.mcp'))
        ->assertOk()
        ->assertSee(['Remote transport', 'Local transport', 'mcp:start']);
});

it('directs claude desktop users to the connectors ui, not the config file', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($user)
        ->get(route('settings.mcp'))
        ->assertOk()
        ->assertSee(['Claude Desktop', 'Settings', 'Connectors', 'Add custom connector']);
});

it('hides write tools from the catalogue when the writes kill switch is off', function () {
    config(['mcp.writes_enabled' => false]);

    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($user)
        ->get(route('settings.mcp'))
        ->assertOk()
        ->assertDontSee('invoices.create')
        ->assertDontSee('invoices.delete')
        ->assertSee('invoices.list');
});

it('shows the live prompt catalogue', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $this->actingAs($user)
        ->get(route('settings.mcp'))
        ->assertOk()
        ->assertSee([
            'draft-invoice',
            'chase-overdue',
            'month-end-review',
        ]);
});
