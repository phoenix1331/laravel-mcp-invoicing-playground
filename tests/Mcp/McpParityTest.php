<?php

declare(strict_types=1);

use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Support\CapabilityMap;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use PHPUnit\Framework\Assert;

/**
 * @return array<int, string>
 */
function namedApplicationRoutes(): array
{
    return collect(Route::getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->unique()
        ->values()
        ->all();
}

/**
 * @return array<int, string>
 */
function registeredMcpToolNames(): array
{
    $server = new InvoicingServer(new FakeTransporter);

    return $server->createContext()->tools()
        ->map(fn ($tool) => $tool->name())
        ->all();
}

it('maps or exempts every named application route', function () {
    $unaccountedFor = collect(namedApplicationRoutes())
        ->reject(fn (string $name) => CapabilityMap::toolFor($name) !== null || CapabilityMap::isExempt($name));

    expect($unaccountedFor)->toBeEmpty(
        'The following named routes are missing from CapabilityMap: '.$unaccountedFor->implode(', ')
    );
});

it('never maps a route that no longer exists', function () {
    $named = namedApplicationRoutes();

    $stale = collect(CapabilityMap::mapped())
        ->keys()
        ->merge(array_keys(CapabilityMap::exempt()))
        ->reject(fn (string $name) => in_array($name, $named, true));

    expect($stale)->toBeEmpty(
        'CapabilityMap references routes that no longer exist: '.$stale->implode(', ')
    );
});

it('registers a tool for every currently-built read route', function () {
    $readRoutes = [
        'invoices.index',
        'invoices.show',
        'invoices.pdf',
        'customers.index',
        'customers.show',
    ];

    $registered = registeredMcpToolNames();

    foreach ($readRoutes as $routeName) {
        $tool = CapabilityMap::toolFor($routeName);

        Assert::assertNotNull($tool, "No CapabilityMap entry for [{$routeName}].");
        Assert::assertContains($tool, $registered, "Tool [{$tool}] mapped from [{$routeName}] is not registered on InvoicingServer.");
    }
});
