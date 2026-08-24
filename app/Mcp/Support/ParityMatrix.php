<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use Illuminate\Support\Facades\Route;

/**
 * Builds the route <-> tool parity matrix consumed by both the
 * /settings/mcp console and the artisan docs:capability-map command, so
 * there is exactly one place that walks the route table against
 * CapabilityMap and the live server catalogue.
 */
class ParityMatrix
{
    /**
     * @param  array<int, string>  $registeredToolNames
     * @return array<int, array{route: string, tool: string|null, reason: string|null, satisfied: bool}>
     */
    public static function build(array $registeredToolNames): array
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $routes->map(function (string $routeName) use ($registeredToolNames) {
            $tool = CapabilityMap::toolFor($routeName);

            if ($tool !== null) {
                return [
                    'route' => $routeName,
                    'tool' => $tool,
                    'reason' => null,
                    'satisfied' => in_array($tool, $registeredToolNames, true),
                ];
            }

            $reason = CapabilityMap::exemptionReason($routeName);

            return [
                'route' => $routeName,
                'tool' => null,
                'reason' => $reason,
                'satisfied' => $reason !== null,
            ];
        })->all();
    }
}
