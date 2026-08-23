<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mcp\Servers\InvoicingServer;
use Illuminate\Contracts\View\View;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Transport\FakeTransporter;

class McpConsoleController extends Controller
{
    /**
     * Display the MCP console: connection details and the live tool,
     * resource and prompt catalogue read from the server at runtime.
     */
    public function __invoke(): View
    {
        $server = new InvoicingServer(new FakeTransporter);
        $context = $server->createContext();

        return view('settings.mcp', [
            'webUrl' => url('/mcp/invoicing'),
            'localHandle' => 'invoicing',
            'tools' => $context->tools()->map(fn (Primitive $tool) => $tool->toArray()),
            'resources' => $context->resources()->map(fn (Primitive $resource) => $resource->toArray()),
            'resourceTemplates' => $context->resourceTemplates()->map(fn (Primitive $resource) => $resource->toArray()),
            'prompts' => $context->prompts()->map(fn (Primitive $prompt) => $prompt->toArray()),
        ]);
    }
}
