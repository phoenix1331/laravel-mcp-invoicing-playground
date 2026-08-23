<?php

declare(strict_types=1);

namespace App\Mcp\Middleware;

use App\Models\McpAuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuditMcpCalls
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $payload = json_decode($request->getContent(), associative: true);
        $isToolCall = is_array($payload) && ($payload['method'] ?? null) === 'tools/call';

        $startedAt = microtime(true);

        /** @var SymfonyResponse $response */
        $response = $next($request);

        if (! $isToolCall) {
            return $response;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        /** @var User|null $user */
        $user = $request->user();

        McpAuditLog::create([
            'organisation_id' => $user?->organisation_id,
            'user_id' => $user?->id,
            'transport' => 'web',
            'tool_name' => data_get($payload, 'params.name'),
            'arguments' => data_get($payload, 'params.arguments', []),
            'outcome' => $this->outcomeFor($response),
            'error' => $this->errorFor($response),
            'duration_ms' => $durationMs,
            'ip_address' => $request->ip(),
        ]);

        return $response;
    }

    private function outcomeFor(SymfonyResponse $response): string
    {
        if (! $response instanceof Response) {
            return 'streamed';
        }

        $body = json_decode($response->getContent() ?: '', associative: true);

        if (! is_array($body)) {
            return 'unknown';
        }

        if (array_key_exists('error', $body)) {
            return 'error';
        }

        if (data_get($body, 'result.isError') === true) {
            return 'error';
        }

        return 'success';
    }

    private function errorFor(SymfonyResponse $response): ?string
    {
        if (! $response instanceof Response) {
            return null;
        }

        $body = json_decode($response->getContent() ?: '', associative: true);

        if (! is_array($body)) {
            return null;
        }

        if ($message = data_get($body, 'error.message')) {
            return (string) $message;
        }

        if (data_get($body, 'result.isError') === true) {
            /** @var array<int, array<string, mixed>> $content */
            $content = data_get($body, 'result.content', []);

            $contents = collect($content)
                ->pluck('text')
                ->filter()
                ->implode(' ');

            return $contents !== '' ? $contents : 'The tool returned an error.';
        }

        return null;
    }
}
