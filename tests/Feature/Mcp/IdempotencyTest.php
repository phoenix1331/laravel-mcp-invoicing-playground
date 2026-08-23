<?php

declare(strict_types=1);

use App\Mcp\Support\Idempotency;
use Laravel\Mcp\Response;

it('runs the callback and returns its result when no key is given', function () {
    $calls = 0;

    $idempotency = app(Idempotency::class);

    $response = $idempotency->remember('test.tool', null, 1, function () use (&$calls) {
        $calls++;

        return Response::make(Response::text('done'))->withStructuredContent(['count' => $calls]);
    });

    expect($calls)->toBe(1);
    expect($response->getStructuredContent())->toBe(['count' => 1]);
});

it('runs the callback only once for the same key and replays the result', function () {
    $calls = 0;

    $idempotency = app(Idempotency::class);

    $callback = function () use (&$calls) {
        $calls++;

        return Response::make(Response::text("call {$calls}"))->withStructuredContent(['count' => $calls]);
    };

    $first = $idempotency->remember('test.tool', 'shared-key', 1, $callback);
    $second = $idempotency->remember('test.tool', 'shared-key', 1, $callback);

    expect($calls)->toBe(1);
    expect($first->getStructuredContent())->toBe(['count' => 1]);
    expect($second->getStructuredContent())->toBe(['count' => 1]);
});

it('treats an empty string key the same as no key', function () {
    $calls = 0;

    $idempotency = app(Idempotency::class);

    $callback = function () use (&$calls) {
        $calls++;

        return Response::text("call {$calls}");
    };

    $idempotency->remember('test.tool', '', 1, $callback);
    $idempotency->remember('test.tool', '', 1, $callback);

    expect($calls)->toBe(2);
});

it('does not replay across different tool names', function () {
    $calls = 0;

    $idempotency = app(Idempotency::class);

    $callback = function () use (&$calls) {
        $calls++;

        return Response::text("call {$calls}");
    };

    $idempotency->remember('tool.one', 'same-key', 1, $callback);
    $idempotency->remember('tool.two', 'same-key', 1, $callback);

    expect($calls)->toBe(2);
});

it('does not replay across different organisations', function () {
    $calls = 0;

    $idempotency = app(Idempotency::class);

    $callback = function () use (&$calls) {
        $calls++;

        return Response::text("call {$calls}");
    };

    $idempotency->remember('test.tool', 'same-key', 1, $callback);
    $idempotency->remember('test.tool', 'same-key', 2, $callback);

    expect($calls)->toBe(2);
});

it('replays a plain text response without structured content', function () {
    $calls = 0;

    $idempotency = app(Idempotency::class);

    $callback = function () use (&$calls) {
        $calls++;

        return Response::text('plain summary');
    };

    $idempotency->remember('test.tool', 'plain-key', 1, $callback);
    $second = $idempotency->remember('test.tool', 'plain-key', 1, $callback);

    expect($calls)->toBe(1);
    expect((string) $second->content())->toBe('plain summary');
});
