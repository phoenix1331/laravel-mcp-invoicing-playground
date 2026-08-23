<x-layout title="API tokens">
    <h1 class="text-2xl font-semibold text-[#0a2540]">API tokens</h1>
    <p class="mt-1 text-sm text-[#425466]">Create a token here to connect an MCP client such as Claude Desktop or Cursor to this account.</p>

    @if (session('plainTextToken'))
        <div class="mt-6 rounded-lg border border-[#f87171] bg-[#fff5f5] p-4">
            <p class="text-sm font-medium text-[#0a2540]">Your new token</p>
            <p class="mt-1 text-sm text-[#425466]">Copy it now - it will not be shown again.</p>
            <code class="mt-2 block break-all rounded-md bg-white px-3 py-2 text-sm text-[#0a2540] ring-1 ring-[#e3e8ee]">{{ session('plainTextToken') }}</code>
        </div>
    @endif

    <div class="mt-6 rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <h2 class="text-sm font-medium text-[#0a2540]">Create a new token</h2>

        <form method="POST" action="{{ route('settings.tokens.store') }}" class="mt-4 flex items-end gap-3">
            @csrf

            <div class="flex-1">
                <x-form.input label="Token name" name="name" value="{{ old('name') }}" placeholder="e.g. Claude Desktop" required autofocus />
            </div>

            <div class="w-40">
                <x-form.button type="submit">Create token</x-form.button>
            </div>
        </form>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Last used</th>
                    <th class="px-6 py-3">Created</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                @forelse ($tokens as $token)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-[#0a2540]">{{ $token->name }}</td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $token->created_at?->diffForHumans() }}</td>
                        <td class="px-6 py-4 text-right text-sm">
                            <form method="POST" action="{{ route('settings.tokens.destroy', $token) }}" onsubmit="return confirm('Revoke this token? Any client using it will lose access immediately.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-[#df1b41] hover:text-[#b91c3c]">Revoke</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-sm text-[#8792a2]">No tokens yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
