<x-auth-layout title="Authorize application">
    <h1 class="mb-2 text-lg font-semibold text-[#0a2540]">Authorize {{ $client->name }}</h1>
    <p class="mb-6 text-sm text-[#425466]">
        <strong class="text-[#0a2540]">{{ $client->name }}</strong> is requesting access to your
        {{ config('app.name') }} account as <strong class="text-[#0a2540]">{{ $user->email }}</strong>.
    </p>

    @if (count($scopes) > 0)
        <div class="mb-6 rounded-md border border-[#e3e8ee] bg-[#f6f9fc] p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-[#8792a2]">This will allow the application to</p>
            <ul class="mt-2 space-y-1 text-sm text-[#425466]">
                @foreach ($scopes as $scope)
                    <li class="flex items-start gap-2">
                        <span aria-hidden="true" class="text-[#0e9f6e]">&check;</span>
                        {{ $scope->description }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex gap-3">
        <form method="POST" action="{{ url('/oauth/authorize') }}" class="flex-1">
            @csrf
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <x-form.button type="submit">Authorize</x-form.button>
        </form>

        <form method="POST" action="{{ url('/oauth/authorize') }}" class="flex-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button
                type="submit"
                class="w-full rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#425466] transition hover:bg-[#f6f9fc]"
            >
                Cancel
            </button>
        </form>
    </div>
</x-auth-layout>
