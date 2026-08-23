<?php

declare(strict_types=1);

use App\Models\McpAuditLog;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->user = User::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('logs a successful tool call', function () {
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/mcp/invoicing', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'organisation.get',
                'arguments' => [],
            ],
        ]);

    $response->assertOk();

    expect(McpAuditLog::count())->toBe(1);

    $log = McpAuditLog::first();

    expect($log->organisation_id)->toBe($this->organisation->id)
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->transport)->toBe('web')
        ->and($log->tool_name)->toBe('organisation.get')
        ->and($log->outcome)->toBe('success')
        ->and($log->error)->toBeNull()
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('logs a tool call that returns a tool-level error', function () {
    $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/mcp/invoicing', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'invoices.get',
                'arguments' => ['invoice_id' => 999999],
            ],
        ]);

    $log = McpAuditLog::first();

    expect($log->outcome)->toBe('error')
        ->and($log->error)->toContain('No invoice was found');
});

it('logs a protocol-level error for an unknown tool', function () {
    $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/mcp/invoicing', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'not-a-real-tool',
                'arguments' => [],
            ],
        ]);

    $log = McpAuditLog::first();

    expect($log->outcome)->toBe('error')
        ->and($log->error)->not->toBeNull();
});

it('does not log non-tool-call methods', function () {
    $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/mcp/invoicing', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
            'params' => [],
        ]);

    expect(McpAuditLog::count())->toBe(0);
});

it('records the arguments the tool was called with', function () {
    $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson('/mcp/invoicing', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'invoices.get',
                'arguments' => ['invoice_id' => 42],
            ],
        ]);

    $log = McpAuditLog::first();

    expect($log->arguments)->toBe(['invoice_id' => 42]);
});
