@php
    $outcomeColors = [
        'success' => 'bg-emerald-50 text-emerald-700',
        'error' => 'bg-[#fff5f5] text-[#df1b41]',
        'streamed' => 'bg-indigo-50 text-indigo-700',
        'unknown' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<x-layout title="MCP activity">
    <h1 class="text-2xl font-semibold text-[#0a2540]">MCP activity</h1>
    <p class="mt-1 text-sm text-[#425466]">
        Every MCP tool call made against this organisation: who called it, with what arguments, what happened, and how long it took.
    </p>

    <form method="GET" action="{{ route('audit.mcp') }}" class="mt-6 grid grid-cols-1 gap-4 rounded-lg bg-white p-4 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5 sm:grid-cols-4">
        <select name="tool_name" class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]">
            <option value="">All tools</option>
            @foreach ($toolNames as $toolName)
                <option value="{{ $toolName }}" @selected(request('tool_name') === $toolName)>{{ $toolName }}</option>
            @endforeach
        </select>

        <select name="outcome" class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]">
            <option value="">All outcomes</option>
            @foreach (['success', 'error', 'streamed', 'unknown'] as $outcome)
                <option value="{{ $outcome }}" @selected(request('outcome') === $outcome)>{{ ucfirst($outcome) }}</option>
            @endforeach
        </select>

        <div class="sm:col-span-2 flex justify-end gap-3">
            <a href="{{ route('audit.mcp') }}" class="rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#0a2540] hover:bg-[#f6f9fc]">
                Clear
            </a>
            <button type="submit" class="rounded-md bg-[#f87171] px-4 py-2 text-sm font-medium text-white hover:bg-[#ef4444]">
                Filter
            </button>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-6 py-3">When</th>
                    <th class="px-6 py-3">Who</th>
                    <th class="px-6 py-3">Tool</th>
                    <th class="px-6 py-3">Arguments</th>
                    <th class="px-6 py-3">Outcome</th>
                    <th class="px-6 py-3 text-right">Duration</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-6 py-4 text-sm text-[#425466]" title="{{ $log->created_at }}">{{ $log->created_at?->diffForHumans() }}</td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $log->user?->name ?? 'Unauthenticated' }}</td>
                        <td class="px-6 py-4 text-sm font-medium"><code class="text-[#0a2540]">{{ $log->tool_name ?? 'unknown' }}</code></td>
                        <td class="px-6 py-4 text-sm text-[#425466]">
                            @if (! empty($log->arguments))
                                <details>
                                    <summary class="cursor-pointer">{{ count($log->arguments) }} {{ Str::plural('argument', count($log->arguments)) }}</summary>
                                    <pre class="mt-1 overflow-x-auto rounded-md bg-[#f6f9fc] px-3 py-2 text-xs text-[#0a2540]">{{ json_encode($log->arguments, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @else
                                <span class="text-[#8792a2]">None</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $outcomeColors[$log->outcome] ?? $outcomeColors['unknown'] }}" title="{{ $log->error }}">
                                {{ ucfirst($log->outcome) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right text-sm tabular-nums text-[#0a2540]">{{ $log->duration_ms }}ms</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-[#8792a2]">No MCP activity yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</x-layout>
