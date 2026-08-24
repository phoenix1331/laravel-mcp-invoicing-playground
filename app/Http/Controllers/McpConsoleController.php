<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Support\ParityMatrix;
use Illuminate\Contracts\View\View;
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
            'parityMatrix' => ParityMatrix::build($registeredToolNames),
        ]);
    }
}
