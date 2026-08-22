<x-layout :title="'Edit invoice ' . $invoice->number">
    <h1 class="text-2xl font-semibold text-[#0a2540]">{{ $invoice->number }}</h1>

    <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="mt-6">
        @csrf
        @method('PUT')

        @include('invoices._form', ['invoice' => $invoice, 'customers' => $customers])
    </form>
</x-layout>
