<x-auth-layout title="Reset password">
    <h1 class="mb-6 text-lg font-semibold text-[#0a2540]">Reset your password</h1>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input label="Email" name="email" type="email" value="{{ $request->email }}" required autofocus />
        <x-form.input label="Password" name="password" type="password" required />
        <x-form.input label="Confirm password" name="password_confirmation" type="password" required />

        <x-form.button>Reset password</x-form.button>
    </form>
</x-auth-layout>
