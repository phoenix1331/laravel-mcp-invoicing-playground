<x-layout :title="$customer->name">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[#0a2540]">{{ $customer->name }}</h1>
            <p class="mt-1 text-sm text-[#425466]">{{ $customer->email }}</p>
        </div>

        <div class="flex gap-3">
            @can('update', $customer)
                <a
                    href="{{ route('customers.edit', $customer) }}"
                    class="rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#0a2540] hover:bg-[#f6f9fc]"
                >
                    Edit
                </a>
            @endcan

            @can('delete', $customer)
                <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-[#df1b41] px-4 py-2 text-sm font-medium text-[#df1b41] hover:bg-[#df1b41] hover:text-white">
                        Delete
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="mt-6 rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-[#8792a2]">Email</dt>
                <dd class="mt-1 text-[#0a2540]">{{ $customer->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[#8792a2]">Address</dt>
                <dd class="mt-1 text-[#0a2540]">{{ $customer->address ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <h2 class="mt-8 text-lg font-semibold text-[#0a2540]">Invoices</h2>

    <div class="mt-4 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-6 py-3">Number</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Issue date</th>
                    <th class="px-6 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                @forelse ($customer->invoices as $invoice)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-[#0a2540]">{{ $invoice->number }}</td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ ucfirst($invoice->status->value) }}</td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $invoice->issue_date->format('d M Y') }}</td>
                        <td class="px-6 py-4 text-right text-sm tabular-nums text-[#0a2540]">£{{ number_format($invoice->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-sm text-[#8792a2]">No invoices for this customer yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
