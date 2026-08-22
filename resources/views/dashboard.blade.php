@php
    $maxRevenue = max(1, collect($summary['revenue_by_month'])->max('total'));
    $chartHeight = 160;
    $barWidth = 40;
    $barGap = 24;
    $chartWidth = (count($summary['revenue_by_month']) * ($barWidth + $barGap));

    $statusColors = [
        'draft' => 'bg-slate-100 text-slate-700',
        'sent' => 'bg-indigo-50 text-indigo-700',
        'paid' => 'bg-emerald-50 text-emerald-700',
        'void' => 'bg-amber-50 text-amber-700',
    ];
@endphp

<x-layout title="Dashboard">
    <h1 class="text-2xl font-semibold text-[#0a2540]">Dashboard</h1>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
            <p class="text-sm text-[#8792a2]">Outstanding</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-[#0a2540]">£{{ number_format($summary['outstanding'], 2) }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
            <p class="text-sm text-[#8792a2]">Overdue</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-[#df1b41]">£{{ number_format($summary['overdue'], 2) }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
            <p class="text-sm text-[#8792a2]">Paid this month</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-[#0e9f6e]">£{{ number_format($summary['paid_this_month'], 2) }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
            <p class="text-sm text-[#8792a2]">Drafts</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-[#0a2540]">{{ $summary['drafts'] }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <h2 class="text-sm font-medium text-[#8792a2]">Revenue, last 6 months</h2>

        <svg
            viewBox="0 0 {{ $chartWidth }} {{ $chartHeight + 24 }}"
            class="mt-4 h-40 w-full"
            role="img"
            aria-label="Bar chart of paid revenue over the last six months"
        >
            @foreach ($summary['revenue_by_month'] as $index => $month)
                @php
                    $barHeight = $maxRevenue > 0 ? ($month['total'] / $maxRevenue) * $chartHeight : 0;
                    $x = $index * ($barWidth + $barGap);
                    $y = $chartHeight - $barHeight;
                @endphp
                <rect
                    x="{{ $x }}"
                    y="{{ $y }}"
                    width="{{ $barWidth }}"
                    height="{{ $barHeight }}"
                    rx="4"
                    fill="#f87171"
                >
                    <title>{{ $month['month'] }}: £{{ number_format($month['total'], 2) }}</title>
                </rect>
                <text
                    x="{{ $x + $barWidth / 2 }}"
                    y="{{ $chartHeight + 18 }}"
                    text-anchor="middle"
                    font-size="12"
                    fill="#8792a2"
                >{{ $month['month'] }}</text>
            @endforeach
        </svg>
    </div>

    <h2 class="mt-8 text-lg font-semibold text-[#0a2540]">Recent invoices</h2>

    <div class="mt-4 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-6 py-3">Number</th>
                    <th class="px-6 py-3">Customer</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Issue date</th>
                    <th class="px-6 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                @forelse ($recentInvoices as $invoice)
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
                        <td class="px-6 py-4 text-right text-sm tabular-nums text-[#0a2540]">£{{ number_format($invoice->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-[#8792a2]">No invoices yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
