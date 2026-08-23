<?php

declare(strict_types=1);

use App\Models\McpAuditLog;
use App\Models\Organisation;
use App\Models\User;

it('redirects a guest away from the mcp activity log', function () {
    $this->get(route('audit.mcp'))->assertRedirect('/login');
});

it('shows who what outcome and duration for each call', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Ada Lovelace']);

    McpAuditLog::create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'transport' => 'web',
        'tool_name' => 'invoices.create',
        'arguments' => ['customer_id' => 1],
        'outcome' => 'success',
        'error' => null,
        'duration_ms' => 42,
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($user)
        ->get(route('audit.mcp'))
        ->assertOk()
        ->assertSee(['Ada Lovelace', 'invoices.create', 'Success', '42ms']);
});

it('only shows activity belonging to the authenticated user organisation', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $otherOrganisation = Organisation::factory()->create();
    $otherUser = User::factory()->create(['organisation_id' => $otherOrganisation->id]);

    McpAuditLog::create([
        'organisation_id' => $otherOrganisation->id,
        'user_id' => $otherUser->id,
        'transport' => 'web',
        'tool_name' => 'customers.delete',
        'arguments' => [],
        'outcome' => 'success',
        'error' => null,
        'duration_ms' => 10,
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($user)->get(route('audit.mcp'));

    $response->assertOk();

    expect($response->viewData('logs'))->toHaveCount(0);
});

it('filters by tool name', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    McpAuditLog::create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'transport' => 'web',
        'tool_name' => 'invoices.create',
        'arguments' => [],
        'outcome' => 'success',
        'error' => null,
        'duration_ms' => 5,
        'ip_address' => '127.0.0.1',
    ]);

    McpAuditLog::create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'transport' => 'web',
        'tool_name' => 'customers.delete',
        'arguments' => [],
        'outcome' => 'success',
        'error' => null,
        'duration_ms' => 5,
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($user)->get(route('audit.mcp', ['tool_name' => 'invoices.create']));

    $response->assertOk();

    $logs = $response->viewData('logs');

    expect($logs)->toHaveCount(1)
        ->and($logs->first()->tool_name)->toBe('invoices.create');
});

it('filters by outcome', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    McpAuditLog::create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'transport' => 'web',
        'tool_name' => 'invoices.get',
        'arguments' => [],
        'outcome' => 'error',
        'error' => 'No invoice was found.',
        'duration_ms' => 5,
        'ip_address' => '127.0.0.1',
    ]);

    McpAuditLog::create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'transport' => 'web',
        'tool_name' => 'invoices.get',
        'arguments' => [],
        'outcome' => 'success',
        'error' => null,
        'duration_ms' => 5,
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($user)->get(route('audit.mcp', ['outcome' => 'error']));

    $response->assertOk();

    $logs = $response->viewData('logs');

    expect($logs)->toHaveCount(1)
        ->and($logs->first()->outcome)->toBe('error');
});
