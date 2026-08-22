<x-layout title="New invoice">
    <h1 class="text-2xl font-semibold text-[#0a2540]">New invoice</h1>

    <form method="POST" action="{{ route('invoices.store') }}" class="mt-6">
        @csrf

        @include('invoices._form', ['invoice' => null, 'customers' => $customers])
    </form>
</x-layout>
