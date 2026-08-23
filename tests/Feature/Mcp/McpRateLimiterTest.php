<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

function mcpRateLimitFor(User $user, string $toolName): Limit
{
    $limiter = RateLimiter::limiter('mcp');

    $request = Request::create('/mcp/invoicing', 'POST', content: json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => $toolName, 'arguments' => []],
    ]));

    $request->setUserResolver(fn () => $user);

    return app()->call($limiter, ['request' => $request]);
}

it('applies a tighter limit to a write tool than a read tool', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $readLimit = mcpRateLimitFor($user, 'invoices.list');
    $writeLimit = mcpRateLimitFor($user, 'invoices.create');

    expect($writeLimit->maxAttempts)->toBeLessThan($readLimit->maxAttempts);
});

it('keys write and read limits separately for the same user', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $readLimit = mcpRateLimitFor($user, 'invoices.list');
    $writeLimit = mcpRateLimitFor($user, 'invoices.create');

    expect($readLimit->key)->not->toBe($writeLimit->key);
});

it('treats an unrecognised method as a read', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $limiter = RateLimiter::limiter('mcp');

    $request = Request::create('/mcp/invoicing', 'POST', content: json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => ['uri' => 'invoicing://guidelines'],
    ]));

    $request->setUserResolver(fn () => $user);

    $limit = app()->call($limiter, ['request' => $request]);
    $readLimit = mcpRateLimitFor($user, 'invoices.list');

    expect($limit->maxAttempts)->toBe($readLimit->maxAttempts);
});

it('returns 429 after the write limit is exhausted, over real HTTP', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);
    $token = $user->createToken('test')->plainTextToken;

    $writeLimit = mcpRateLimitFor($user, 'customers.create')->maxAttempts;

    $statuses = [];

    for ($i = 0; $i < $writeLimit + 1; $i++) {
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/mcp/invoicing', [
                'jsonrpc' => '2.0',
                'id' => $i,
                'method' => 'tools/call',
                'params' => ['name' => 'customers.create', 'arguments' => ['name' => "Customer {$i}"]],
            ]);

        $statuses[] = $response->getStatusCode();
    }

    expect($statuses)->toHaveCount($writeLimit + 1)
        ->and(array_slice($statuses, 0, $writeLimit))->each->toBe(200)
        ->and($statuses[$writeLimit])->toBe(429);
});
