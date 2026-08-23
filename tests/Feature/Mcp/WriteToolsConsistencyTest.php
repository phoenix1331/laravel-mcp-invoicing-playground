<?php

declare(strict_types=1);

use App\Mcp\Concerns\IsWriteTool;
use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Support\WriteTools;
use Laravel\Mcp\Server\Transport\FakeTransporter;

/**
 * WriteTools::NAMES is a static list kept in sync by hand with which tool
 * classes actually use the IsWriteTool trait - this guards against that
 * list silently drifting, the same purpose McpParityTest serves for routes.
 */
it('lists every registered tool that uses IsWriteTool, and nothing else', function () {
    $server = new InvoicingServer(new FakeTransporter);

    $toolsUsingTrait = $server->createContext()->tools()
        ->filter(fn ($tool) => in_array(IsWriteTool::class, class_uses_recursive($tool::class), true))
        ->map(fn ($tool) => $tool->name())
        ->sort()
        ->values()
        ->all();

    $declared = collect(WriteTools::NAMES)->sort()->values()->all();

    expect($toolsUsingTrait)->toBe($declared);
});
