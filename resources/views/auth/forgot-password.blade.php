<x-auth-layout title="Forgot password">
    <h1 class="mb-2 text-lg font-semibold text-[#0a2540]">Forgot your password?</h1>
    <p class="mb-6 text-sm text-[#425466]">Enter your email and we'll send you a link to reset it.</p>

    @if (session('status'))
        <p class="mb-4 text-sm text-[#0e9f6e]">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-form.input label="Email" name="email" type="email" value="{{ old('email') }}" required autofocus />

        <x-form.button>Email password reset link</x-form.button>
    </form>

    <p class="mt-6 text-center text-sm text-[#8792a2]">
        <a href="{{ route('login') }}" class="font-medium text-[#635bff] hover:text-[#5147e5]">Back to log in</a>
    </p>
</x-auth-layout>
