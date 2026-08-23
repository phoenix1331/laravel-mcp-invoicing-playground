<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Replays a write tool's result for 24h when the caller supplies the same
 * idempotency_key again, instead of repeating the mutation. LLM clients
 * retry on timeouts and ambiguous errors; without this, a retried
 * invoices.create call would silently create two invoices.
 *
 * Deliberately scoped by tool name, organisation and key together, so two
 * different organisations (or two different tools) reusing the same key
 * string never collide.
 */
class Idempotency
{
    private const TTL_HOURS = 24;

    /**
     * @param  Closure(): (Response|ResponseFactory)  $callback
     */
    public function remember(string $toolName, ?string $idempotencyKey, int $organisationId, Closure $callback): Response|ResponseFactory
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $callback();
        }

        $cacheKey = $this->cacheKey($toolName, $idempotencyKey, $organisationId);

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && array_key_exists('text', $cached) && array_key_exists('structured', $cached)) {
            /** @var array{text: string, structured: array<string, mixed>|null} $cached */
            return $this->toResponse($cached);
        }

        $response = $callback();

        if (! $this->isConfirmationPrompt($response)) {
            Cache::put($cacheKey, $this->toCacheable($response), now()->addHours(self::TTL_HOURS));
        }

        return $response;
    }

    /**
     * A ConfirmationGate prompt is not a completed call - the mutation it
     * describes never happened, so it must never be cached as the result
     * for this key. The next call (confirmed or not) needs to run for real.
     */
    private function isConfirmationPrompt(Response|ResponseFactory $response): bool
    {
        $factory = $response instanceof Response ? Response::make($response) : $response;

        return ($factory->getStructuredContent()['requires_confirmation'] ?? false) === true;
    }

    private function cacheKey(string $toolName, string $idempotencyKey, int $organisationId): string
    {
        return "mcp-idempotency:{$toolName}:{$organisationId}:{$idempotencyKey}";
    }

    /**
     * @return array{text: string, structured: array<string, mixed>|null}
     */
    private function toCacheable(Response|ResponseFactory $response): array
    {
        $factory = $response instanceof Response ? Response::make($response) : $response;

        $text = $factory->responses()
            ->map(fn (Response $item): string => (string) $item->content())
            ->implode(' ');

        return [
            'text' => $text,
            'structured' => $factory->getStructuredContent(),
        ];
    }

    /**
     * @param  array{text: string, structured: array<string, mixed>|null}  $cached
     */
    private function toResponse(array $cached): Response|ResponseFactory
    {
        $summary = Response::text($cached['text']);

        if ($cached['structured'] === null) {
            return $summary;
        }

        return Response::make($summary)->withStructuredContent($cached['structured']);
    }
}
