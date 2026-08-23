<x-layout title="Team">
    <h1 class="text-2xl font-semibold text-[#0a2540]">Team</h1>
    <p class="mt-1 text-sm text-[#425466]">Invite members and manage roles. Owner role only.</p>

    @if (session('temporaryPassword'))
        <div class="mt-6 rounded-lg border border-[#f87171] bg-[#fff5f5] p-4">
            <p class="text-sm font-medium text-[#0a2540]">Temporary password</p>
            <p class="mt-1 text-sm text-[#425466]">Relay this to the new member securely - it will not be shown again.</p>
            <div x-data="{ copied: false }" class="mt-2 flex items-center gap-2">
                <code x-ref="temporaryPassword" class="block flex-1 break-all rounded-md bg-white px-3 py-2 text-sm text-[#0a2540] ring-1 ring-[#e3e8ee]">{{ session('temporaryPassword') }}</code>
                <button
                    type="button"
                    @click="navigator.clipboard.writeText($refs.temporaryPassword.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="shrink-0 rounded-md border border-[#e3e8ee] bg-white px-3 py-1.5 text-xs font-medium text-[#425466] hover:bg-[#f6f9fc]"
                >
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
            </div>
        </div>
    @endif

    <div class="mt-6 rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <h2 class="text-sm font-medium text-[#0a2540]">Invite a member</h2>

        <form method="POST" action="{{ route('settings.team.store') }}" class="mt-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-form.input label="Name" name="name" value="{{ old('name') }}" required autofocus />
                <x-form.input label="Email" name="email" type="email" value="{{ old('email') }}" required />

                <div>
                    <label for="role" class="mb-1.5 block text-sm font-medium text-[#0a2540]">Role</label>
                    <select
                        id="role"
                        name="role"
                        required
                        class="block w-full rounded-md border border-[#e3e8ee] bg-white px-3 py-2 text-sm text-[#0a2540] shadow-sm focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
                    >
                        @foreach (App\Enums\UserRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ ucfirst($role->value) }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <p class="mt-1.5 text-sm text-[#df1b41]">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 max-w-xs">
                <x-form.button type="submit">Send invite</x-form.button>
            </div>
        </form>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                @foreach ($members as $member)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-[#0a2540]">{{ $member->name }}</td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $member->email }}</td>
                        <td class="px-6 py-4 text-sm">
                            <form method="POST" action="{{ route('settings.team.update', $member) }}" class="inline-flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select
                                    name="role"
                                    onchange="this.form.submit()"
                                    class="block rounded-md border border-[#e3e8ee] bg-white px-2 py-1 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
                                >
                                    @foreach (App\Enums\UserRole::cases() as $role)
                                        <option value="{{ $role->value }}" @selected($member->role === $role)>{{ ucfirst($role->value) }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @if ($member->id === auth()->id())
                                <span class="text-xs text-[#8792a2]">(you)</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
