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

    $response = $this->actingAs($user)->get(route('settings.mcp'));

    $response->assertOk();

    $toolNames = $response->viewData('tools')->pluck('name')->all();

    expect($toolNames)->not->toContain('invoices.create')
        ->not->toContain('invoices.delete')
        ->toContain('invoices.list');
});

it('marks write routes as not covered in the parity matrix when the writes kill switch is off', function () {
    config(['mcp.writes_enabled' => false]);

    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $response = $this->actingAs($user)->get(route('settings.mcp'));

    $response->assertOk();

    $row = collect($response->viewData('parityMatrix'))->firstWhere('route', 'invoices.store');

    expect($row)->not->toBeNull()
        ->and($row['tool'])->toBe('invoices.create')
        ->and($row['satisfied'])->toBeFalse();
});

it('marks every mapped route as covered in the parity matrix when writes are enabled', function () {
    config(['mcp.writes_enabled' => true]);

    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $response = $this->actingAs($user)->get(route('settings.mcp'));

    $response->assertOk();

    $unsatisfied = collect($response->viewData('parityMatrix'))->reject(fn ($row) => $row['satisfied']);

    expect($unsatisfied)->toBeEmpty();
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
