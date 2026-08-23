<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Support\CapabilityMap;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Transport\FakeTransporter;

class McpConsoleController extends Controller
{
    /**
     * Display the MCP console: connection details, the live tool, resource
     * and prompt catalogue, and the parity matrix - both read from the
     * server and CapabilityMap at request time, never hardcoded.
     */
    public function __invoke(): View
    {
        $server = new InvoicingServer(new FakeTransporter);
        $context = $server->createContext();

        $registeredToolNames = $context->tools()->map(fn (Primitive $tool) => $tool->name())->all();

        return view('settings.mcp', [
            'webUrl' => url('/mcp/invoicing'),
            'localHandle' => 'invoicing',
            'tools' => $context->tools()->map(fn (Primitive $tool) => $tool->toArray()),
            'resources' => $context->resources()->map(fn (Primitive $resource) => $resource->toArray()),
            'resourceTemplates' => $context->resourceTemplates()->map(fn (Primitive $resource) => $resource->toArray()),
            'prompts' => $context->prompts()->map(fn (Primitive $prompt) => $prompt->toArray()),
            'parityMatrix' => $this->buildParityMatrix($registeredToolNames),
        ]);
    }

    /**
     * @param  array<int, string>  $registeredToolNames
     * @return array<int, array{route: string, tool: string|null, reason: string|null, satisfied: bool}>
     */
    private function buildParityMatrix(array $registeredToolNames): array
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
