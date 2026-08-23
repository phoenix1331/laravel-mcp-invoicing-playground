<x-layout title="Edit customer">
    <h1 class="text-2xl font-semibold text-[#0a2540]">Edit customer</h1>

    <div class="mt-6 rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.input label="Name" name="name" value="{{ old('name', $customer->name) }}" required autofocus />
                <x-form.input label="Email" name="email" type="email" value="{{ old('email', $customer->email) }}" />
                <div class="sm:col-span-2">
                    <x-form.input label="Address" name="address" value="{{ old('address', $customer->address) }}" />
                </div>
            </div>

            <div class="mt-6 max-w-xs">
                <x-form.button>Save changes</x-form.button>
            </div>
        </form>
    </div>
</x-layout>
