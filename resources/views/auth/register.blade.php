<x-auth-layout title="Register">
    <h1 class="mb-6 text-lg font-semibold text-[#0a2540]">Create your account</h1>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf

        <x-form.input label="Name" name="name" value="{{ old('name') }}" required autofocus />
        <x-form.input label="Email" name="email" type="email" value="{{ old('email') }}" required />
        <x-form.input label="Password" name="password" type="password" required />
        <x-form.input label="Confirm password" name="password_confirmation" type="password" required />

        <x-form.button>Register</x-form.button>
    </form>

    <p class="mt-6 text-center text-sm text-[#8792a2]">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-[#f87171] hover:text-[#ef4444]">Log in</a>
    </p>
</x-auth-layout>
