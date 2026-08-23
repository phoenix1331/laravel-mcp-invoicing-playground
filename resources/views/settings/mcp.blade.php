<x-layout title="MCP console">
    <h1 class="text-2xl font-semibold text-[#0a2540]">MCP console</h1>
    <p class="mt-1 text-sm text-[#425466]">Connect an MCP client to this account, and see exactly what it can do.</p>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
            <h2 class="text-sm font-medium text-[#0a2540]">Remote transport (Claude Code, Cursor)</h2>
            <p class="mt-1 text-sm text-[#425466]">
                An HTTP endpoint authenticated with a bearer token. Create one on the
                <a href="{{ route('settings.tokens') }}" class="font-medium text-[#f87171] hover:text-[#ef4444]">API tokens</a> page first.
                These clients read <code>url</code>/<code>headers</code> directly from their MCP config file.
            </p>

            <div x-data="{ copied: false }" class="mt-3">
                <pre class="overflow-x-auto rounded-md bg-[#0a2540] px-4 py-3 text-xs text-[#e3e8ee]"><code x-ref="webConfig">{{ json_encode([
                    'mcpServers' => [
                        'invoicing' => [
                            'url' => $webUrl,
                            'headers' => ['Authorization' => 'Bearer YOUR_TOKEN_HERE'],
                        ],
                    ],
                ], JSON_PRETTY_PRINT) }}</code></pre>
                <button
                    type="button"
                    @click="navigator.clipboard.writeText($refs.webConfig.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="mt-2 rounded-md border border-[#e3e8ee] px-3 py-1.5 text-xs font-medium text-[#425466] hover:bg-[#f6f9fc]"
                >
                    <span x-show="!copied">Copy config</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
            <h2 class="text-sm font-medium text-[#0a2540]">Claude Desktop</h2>
            <p class="mt-1 text-sm text-[#425466]">
                Claude Desktop's config file only starts local (stdio) servers - a remote HTTP server like this one must be
                added via <strong>Settings &rarr; Connectors &rarr; Add custom connector</strong> instead of pasted into
                <code>claude_desktop_config.json</code>. Use the URL and token below in that dialog.
            </p>

            <div class="mt-3 space-y-3">
                <div x-data="{ copied: false }">
                    <p class="text-xs font-medium uppercase tracking-wide text-[#8792a2]">Server URL</p>
                    <div class="mt-1 flex items-center gap-2">
                        <code x-ref="desktopUrl" class="flex-1 overflow-x-auto rounded-md bg-[#0a2540] px-3 py-2 text-xs text-[#e3e8ee]">{{ $webUrl }}</code>
                        <button
                            type="button"
                            @click="navigator.clipboard.writeText($refs.desktopUrl.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                            class="shrink-0 rounded-md border border-[#e3e8ee] px-3 py-1.5 text-xs font-medium text-[#425466] hover:bg-[#f6f9fc]"
                        >
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                    </div>
                </div>

                <p class="text-sm text-[#425466]">
                    Paste your token from the <a href="{{ route('settings.tokens') }}" class="font-medium text-[#f87171] hover:text-[#ef4444]">API tokens</a>
                    page into the connector's Authorization header as <code>Bearer YOUR_TOKEN</code>.
                </p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
            <h2 class="text-sm font-medium text-[#0a2540]">Local transport (Claude Code)</h2>
            <p class="mt-1 text-sm text-[#425466]">A stdio server run inside this project's app container - no token needed, since it runs as you.</p>

            <div x-data="{ copied: false }" class="mt-3">
                <pre class="overflow-x-auto rounded-md bg-[#0a2540] px-4 py-3 text-xs text-[#e3e8ee]"><code x-ref="localConfig">{{ json_encode([
                    'mcpServers' => [
                        'invoicing' => [
                            'command' => 'docker',
                            'args' => ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'mcp:start', $localHandle],
                        ],
                    ],
                ], JSON_PRETTY_PRINT) }}</code></pre>
                <button
                    type="button"
                    @click="navigator.clipboard.writeText($refs.localConfig.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="mt-2 rounded-md border border-[#e3e8ee] px-3 py-1.5 text-xs font-medium text-[#425466] hover:bg-[#f6f9fc]"
                >
                    <span x-show="!copied">Copy config</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-lg bg-white p-4 text-sm text-[#425466] shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <strong class="text-[#0a2540]">Remote vs local:</strong> the remote transport is an authenticated HTTP endpoint for
        clients running anywhere, gated by the bearer token you create on the tokens page. The local transport is a stdio
        process for an agent running on this same machine - it inherits your shell session instead of a token, which is why
        it must run inside this project's container rather than against a URL.
    </div>

    @php
        $unsatisfiedCount = collect($parityMatrix)->reject(fn ($row) => $row['satisfied'])->count();
    @endphp

    <div class="mt-10 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-[#0a2540]">Parity matrix</h2>
        @if ($unsatisfiedCount === 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#e3f9ed] px-3 py-1 text-xs font-medium text-[#0e9f6e]">
                <span aria-hidden="true">&check;</span> Every route is covered
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#fff5f5] px-3 py-1 text-xs font-medium text-[#df1b41]">
                <span aria-hidden="true">&cross;</span> {{ $unsatisfiedCount }} {{ Str::plural('route', $unsatisfiedCount) }} not covered
            </span>
        @endif
    </div>
    <p class="mt-1 text-sm text-[#425466]">
        Every named application route, alongside its MCP tool or its reasoned exemption. Generated at runtime from
        <code>CapabilityMap</code> and the live server catalogue - if a route is added without updating either, this
        page goes red and <code>McpParityTest</code> fails CI.
    </p>

    <div class="mt-3 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-6 py-3">Route</th>
                    <th class="px-6 py-3">MCP tool / exemption</th>
                    <th class="px-6 py-3 text-right">Covered</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                @foreach ($parityMatrix as $row)
                    <tr>
                        <td class="px-6 py-3 text-sm"><code class="text-[#0a2540]">{{ $row['route'] }}</code></td>
                        <td class="px-6 py-3 text-sm text-[#425466]">
                            @if ($row['tool'])
                                <code>{{ $row['tool'] }}</code>
                            @else
                                {{ $row['reason'] }}
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            @if ($row['satisfied'])
                                <span class="text-[#0e9f6e]" title="Covered">&check;</span>
                            @else
                                <span class="text-[#df1b41]" title="Not covered">&cross;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mt-10 text-lg font-semibold text-[#0a2540]">Tools ({{ $tools->count() }})</h2>
    <div class="mt-3 space-y-3">
        @foreach ($tools as $tool)
            <details class="overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
                <summary class="cursor-pointer px-6 py-4 text-sm font-medium text-[#0a2540]">
                    <code>{{ $tool['name'] }}</code>
                    <span class="ml-2 font-normal text-[#425466]">{{ $tool['description'] }}</span>
                </summary>
                <div class="border-t border-[#e3e8ee] px-6 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-[#8792a2]">Input schema</p>
                    <pre class="mt-1 overflow-x-auto rounded-md bg-[#f6f9fc] px-3 py-2 text-xs text-[#0a2540]">{{ json_encode($tool['inputSchema'], JSON_PRETTY_PRINT) }}</pre>

                    @if (isset($tool['outputSchema']))
                        <p class="mt-3 text-xs font-medium uppercase tracking-wide text-[#8792a2]">Output schema</p>
                        <pre class="mt-1 overflow-x-auto rounded-md bg-[#f6f9fc] px-3 py-2 text-xs text-[#0a2540]">{{ json_encode($tool['outputSchema'], JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </div>
            </details>
        @endforeach
    </div>

    <h2 class="mt-10 text-lg font-semibold text-[#0a2540]">Resources ({{ $resources->count() + $resourceTemplates->count() }})</h2>
    <div class="mt-3 space-y-3">
        @foreach ($resources->concat($resourceTemplates) as $resource)
            <div class="rounded-lg bg-white px-6 py-4 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
                <code class="text-sm font-medium text-[#0a2540]">{{ $resource['uri'] ?? $resource['uriTemplate'] }}</code>
                <p class="mt-1 text-sm text-[#425466]">{{ $resource['description'] }}</p>
            </div>
        @endforeach
    </div>

    <h2 class="mt-10 text-lg font-semibold text-[#0a2540]">Prompts ({{ $prompts->count() }})</h2>
    <div class="mt-3 space-y-3">
        @forelse ($prompts as $prompt)
            <div class="rounded-lg bg-white px-6 py-4 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
                <code class="text-sm font-medium text-[#0a2540]">{{ $prompt['name'] }}</code>
                <p class="mt-1 text-sm text-[#425466]">{{ $prompt['description'] }}</p>
            </div>
        @empty
            <p class="rounded-lg bg-white px-6 py-4 text-sm text-[#8792a2] shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">No prompts registered yet.</p>
        @endforelse
    </div>
</x-layout>
