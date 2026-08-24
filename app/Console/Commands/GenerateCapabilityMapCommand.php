<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mcp\Servers\InvoicingServer;
use App\Mcp\Support\ParityMatrix;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Transport\FakeTransporter;

class GenerateCapabilityMapCommand extends Command
{
    protected $signature = 'docs:capability-map';

    protected $description = 'Write docs/data/capability-map.json from the live MCP server catalogue and CapabilityMap - the single source the docs site, /settings/mcp console and McpParityTest all read from.';

    public function handle(): int
    {
        $server = new InvoicingServer(new FakeTransporter);
        $context = $server->createContext();

        $tools = $context->tools()->map(fn (Primitive $tool) => $tool->toArray())->values()->all();
        $resources = $context->resources()->map(fn (Primitive $resource) => $resource->toArray())->values()->all();
        $resourceTemplates = $context->resourceTemplates()->map(fn (Primitive $resource) => $resource->toArray())->values()->all();
        $prompts = $context->prompts()->map(fn (Primitive $prompt) => $prompt->toArray())->values()->all();

        $registeredToolNames = collect($tools)->pluck('name')->all();

        $data = [
            'generatedAt' => now()->toIso8601String(),
            'tools' => $tools,
            'resources' => $resources,
            'resourceTemplates' => $resourceTemplates,
            'prompts' => $prompts,
            'parityMatrix' => ParityMatrix::build($registeredToolNames),
        ];

        $path = base_path('docs/data/capability-map.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info("Wrote {$path}");
        $this->line(sprintf(
            '%d tools, %d resources, %d resource templates, %d prompts, %d parity rows.',
            count($tools),
            count($resources),
            count($resourceTemplates),
            count($prompts),
            count($data['parityMatrix']),
        ));

        return self::SUCCESS;
    }
}
