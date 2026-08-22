<x-layout title="Edit customer">
    <h1 class="text-2xl font-semibold text-[#0a2540]">Edit customer</h1>

    <div class="mt-6 max-w-lg rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-form.input label="Name" name="name" value="{{ old('name', $customer->name) }}" required autofocus />
            <x-form.input label="Email" name="email" type="email" value="{{ old('email', $customer->email) }}" />
            <x-form.input label="Address" name="address" value="{{ old('address', $customer->address) }}" />

            <x-form.button>Save changes</x-form.button>
        </form>
    </div>
</x-layout>
