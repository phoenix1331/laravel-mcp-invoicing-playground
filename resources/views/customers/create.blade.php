<x-layout title="New customer">
    <h1 class="text-2xl font-semibold text-[#0a2540]">New customer</h1>

    <div class="mt-6 max-w-lg rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <form method="POST" action="{{ route('customers.store') }}" class="space-y-4">
            @csrf

            <x-form.input label="Name" name="name" value="{{ old('name') }}" required autofocus />
            <x-form.input label="Email" name="email" type="email" value="{{ old('email') }}" />
            <x-form.input label="Address" name="address" value="{{ old('address') }}" />

            <x-form.button>Create customer</x-form.button>
        </form>
    </div>
</x-layout>
