<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mcp\Support\WriteTools;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            $by = $request->user()?->id ?: $request->ip();

            return $this->isMcpWriteRequest($request)
                ? Limit::perMinute(20)->by("write:{$by}")
                : Limit::perMinute(60)->by("read:{$by}");
        });
    }

    /**
     * Whether an MCP request is a tools/call for a write tool. Tighter
     * throttling applies to writes than reads, per the brief - an LLM
     * client looping on a mistake should hit a low ceiling fast on
     * mutations, while read-heavy exploration stays comfortable.
     *
     * Read directly from the raw request body rather than resolving the
     * server, since this runs on every MCP request and booting the whole
     * InvoicingServer just to check one tool's annotation would be wasteful.
     */
    private function isMcpWriteRequest(Request $request): bool
    {
        $payload = json_decode($request->getContent(), associative: true);

        if (! is_array($payload) || ($payload['method'] ?? null) !== 'tools/call') {
            return false;
        }

        $toolName = data_get($payload, 'params.name');

        return is_string($toolName) && WriteTools::includes($toolName);
    }
}
