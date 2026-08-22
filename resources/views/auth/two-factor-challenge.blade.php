<x-auth-layout title="Two-factor challenge">
    <h1 class="mb-2 text-lg font-semibold text-[#0a2540]">Two-factor confirmation</h1>
    <p class="mb-6 text-sm text-[#425466]" x-data="{ recovery: false }">
        <span x-show="!recovery">Enter the code from your authenticator app.</span>
        <span x-show="recovery" x-cloak>Enter one of your recovery codes.</span>
    </p>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4" x-data="{ recovery: false }">
        @csrf

        <div x-show="!recovery">
            <x-form.input label="Code" name="code" inputmode="numeric" autofocus />
        </div>

        <div x-show="recovery" x-cloak>
            <x-form.input label="Recovery code" name="recovery_code" />
        </div>

        <x-form.button>Log in</x-form.button>

        <button type="button" @click="recovery = !recovery" class="w-full text-center text-sm font-medium text-[#f87171] hover:text-[#ef4444]">
            <span x-show="!recovery">Use a recovery code instead</span>
            <span x-show="recovery" x-cloak>Use an authentication code instead</span>
        </button>
    </form>
</x-auth-layout>
