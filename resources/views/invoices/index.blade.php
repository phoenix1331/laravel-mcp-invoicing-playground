@php
    $statusColors = [
        'draft' => 'bg-slate-100 text-slate-700',
        'sent' => 'bg-indigo-50 text-indigo-700',
        'paid' => 'bg-emerald-50 text-emerald-700',
        'void' => 'bg-amber-50 text-amber-700',
    ];

    $sortLink = fn (string $column) => route('invoices.index', array_merge(
        request()->except(['sort', 'direction', 'page']),
        [
            'sort' => $column,
            'direction' => request('sort') === $column && request('direction', 'desc') === 'desc' ? 'asc' : 'desc',
        ],
    ));
@endphp

<x-layout title="Invoices">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#0a2540]">Invoices</h1>

        @can('create', App\Models\Invoice::class)
            <a
                href="{{ route('invoices.create') }}"
                class="rounded-md bg-[#f87171] px-4 py-2 text-sm font-medium text-white hover:bg-[#ef4444]"
            >
                New invoice
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('invoices.index') }}" class="mt-6 grid grid-cols-1 gap-4 rounded-lg bg-white p-4 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search number or customer"
                class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
            >
        </div>

        <select name="status" class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]">
            <option value="">All statuses</option>
            @foreach (['draft', 'sent', 'paid', 'void'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>

        <select name="customer_id" class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]">
            <option value="">All customers</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>

        <input
            type="date"
            name="from"
            value="{{ request('from') }}"
            class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
        >
        <input
            type="date"
            name="to"
            value="{{ request('to') }}"
            class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
        >

        <div class="sm:col-span-2 lg:col-span-6 flex justify-end gap-3">
            <a href="{{ route('invoices.index') }}" class="rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#0a2540] hover:bg-[#f6f9fc]">
                Clear
            </a>
            <button type="submit" class="rounded-md bg-[#f87171] px-4 py-2 text-sm font-medium text-white hover:bg-[#ef4444]">
                Filter
            </button>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-6 py-3">
                        <a href="{{ $sortLink('number') }}" class="hover:text-[#0a2540]">Number</a>
                    </th>
                    <th class="px-6 py-3">Customer</th>
                    <th class="px-6 py-3">
                        <a href="{{ $sortLink('status') }}" class="hover:text-[#0a2540]">Status</a>
                    </th>
                    <th class="px-6 py-3">
                        <a href="{{ $sortLink('issue_date') }}" class="hover:text-[#0a2540]">Issue date</a>
                    </th>
                    <th class="px-6 py-3">
                        <a href="{{ $sortLink('due_date') }}" class="hover:text-[#0a2540]">Due date</a>
                    </th>
                    <th class="px-6 py-3 text-right">
                        <a href="{{ $sortLink('total') }}" class="hover:text-[#0a2540]">Total</a>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium">
                            <a href="{{ route('invoices.show', $invoice) }}" class="text-[#0a2540] hover:text-[#f87171]">
                                {{ $invoice->number }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $invoice->customer->name }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$invoice->status->value] }}">
                                {{ ucfirst($invoice->status->value) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $invoice->issue_date->format('d M Y') }}</td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $invoice->due_date->format('d M Y') }}</td>
                        <td class="px-6 py-4 text-right text-sm tabular-nums text-[#0a2540]">£{{ number_format($invoice->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-[#8792a2]">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $invoices->links() }}
    </div>
</x-layout>
