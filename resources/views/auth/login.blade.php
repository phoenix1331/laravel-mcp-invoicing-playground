<x-auth-layout title="Log in">
    <h1 class="mb-6 text-lg font-semibold text-[#0a2540]">Log in to your account</h1>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-form.input label="Email" name="email" type="email" value="{{ old('email') }}" required autofocus />
        <x-form.input label="Password" name="password" type="password" required />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-[#425466]">
                <input type="checkbox" name="remember" class="rounded border-[#e3e8ee] text-[#f87171] focus:ring-[#f87171]">
                Remember me
            </label>

            <a href="{{ route('password.request') }}" class="font-medium text-[#f87171] hover:text-[#ef4444]">
                Forgot password?
            </a>
        </div>

        <x-form.button>Log in</x-form.button>
    </form>

    <p class="mt-6 text-center text-sm text-[#8792a2]">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-medium text-[#f87171] hover:text-[#ef4444]">Register</a>
    </p>
</x-auth-layout>
