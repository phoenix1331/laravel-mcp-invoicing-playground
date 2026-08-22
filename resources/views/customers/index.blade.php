<x-layout title="Customers">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#0a2540]">Customers</h1>

        @can('create', App\Models\Customer::class)
            <a
                href="{{ route('customers.create') }}"
                class="rounded-md bg-[#f87171] px-4 py-2 text-sm font-medium text-white hover:bg-[#ef4444]"
            >
                New customer
            </a>
        @endcan
    </div>

    <div class="mt-6 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Invoices</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-6 py-4 text-sm">
                            <a href="{{ route('customers.show', $customer) }}" class="font-medium text-[#0a2540] hover:text-[#f87171]">
                                {{ $customer->name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $customer->email }}</td>
                        <td class="px-6 py-4 text-sm text-[#425466]">{{ $customer->invoices_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-sm text-[#8792a2]">No customers yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</x-layout>
