<x-layout :title="$invoice->number">
    @php
        $statusColors = [
            'draft' => 'bg-slate-100 text-slate-700',
            'sent' => 'bg-indigo-50 text-indigo-700',
            'paid' => 'bg-emerald-50 text-emerald-700',
            'void' => 'bg-amber-50 text-amber-700',
        ];
    @endphp

    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-[#0a2540]">{{ $invoice->number }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$invoice->status->value] }}">
                    {{ ucfirst($invoice->status->value) }}
                </span>
            </div>
            <p class="mt-1 text-sm text-[#425466]">{{ $invoice->customer->name }}</p>
        </div>

        <div class="flex gap-3">
            <button
                type="button"
                disabled
                title="PDF generation lands in a later phase"
                class="cursor-not-allowed rounded-md border border-[#e3e8ee] bg-[#f6f9fc] px-4 py-2 text-sm font-medium text-[#8792a2]"
            >
                Download PDF
            </button>

            @if ($invoice->status->value === 'draft')
                <a
                    href="{{ route('invoices.edit', $invoice) }}"
                    class="rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#0a2540] hover:bg-[#f6f9fc]"
                >
                    Edit
                </a>

                @can('delete', $invoice)
                    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this draft invoice?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-[#df1b41] px-4 py-2 text-sm font-medium text-[#df1b41] hover:bg-[#df1b41] hover:text-white">
                            Delete
                        </button>
                    </form>
                @endcan

                <form method="POST" action="{{ route('invoices.send', $invoice) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-[#f87171] px-4 py-2 text-sm font-medium text-white hover:bg-[#ef4444]">
                        Send
                    </button>
                </form>
            @endif

            @if ($invoice->status->value === 'sent')
                <form method="POST" action="{{ route('invoices.void', $invoice) }}" onsubmit="return confirm('Void this invoice?');">
                    @csrf
                    <button type="submit" class="rounded-md border border-[#df1b41] px-4 py-2 text-sm font-medium text-[#df1b41] hover:bg-[#df1b41] hover:text-white">
                        Void
                    </button>
                </form>

                <form method="POST" action="{{ route('invoices.mark-paid', $invoice) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-[#0e9f6e] px-4 py-2 text-sm font-medium text-white hover:bg-[#0c8a5f]">
                        Mark paid
                    </button>
                </form>
            @endif
        </div>
    </div>

    @error('status')
        <p class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-[#df1b41]">{{ $message }}</p>
    @enderror

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
                <table class="min-w-full divide-y divide-[#e3e8ee]">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Unit price</th>
                            <th class="px-4 py-3 text-right">Line total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e3e8ee]">
                        @foreach ($invoice->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-sm text-[#0a2540]">{{ $line->description }}</td>
                                <td class="px-4 py-3 text-right text-sm tabular-nums text-[#425466]">{{ $line->quantity }}</td>
                                <td class="px-4 py-3 text-right text-sm tabular-nums text-[#425466]">£{{ number_format($line->unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm tabular-nums text-[#0a2540]">£{{ number_format($line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="border-t border-[#e3e8ee] bg-[#f6f9fc] px-4 py-3">
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-[#425466]">Subtotal</dt>
                            <dd class="tabular-nums text-[#0a2540]">£{{ number_format($invoice->subtotal, 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[#425466]">Tax ({{ $invoice->tax_rate }}%)</dt>
                            <dd class="tabular-nums text-[#0a2540]">£{{ number_format($invoice->tax_total, 2) }}</dd>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <dt class="text-[#0a2540]">Total</dt>
                            <dd class="tabular-nums text-[#0a2540]">£{{ number_format($invoice->total, 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if ($invoice->notes)
                <div class="mt-6 rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
                    <h2 class="text-sm font-medium text-[#8792a2]">Notes</h2>
                    <p class="mt-2 text-sm text-[#0a2540]">{{ $invoice->notes }}</p>
                </div>
            @endif
        </div>

        <div>
            <div class="rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
                <h2 class="text-sm font-medium text-[#8792a2]">Details</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    <div>
                        <dt class="text-[#8792a2]">Issue date</dt>
                        <dd class="mt-0.5 text-[#0a2540]">{{ $invoice->issue_date->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#8792a2]">Due date</dt>
                        <dd class="mt-0.5 text-[#0a2540]">{{ $invoice->due_date->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#8792a2]">Currency</dt>
                        <dd class="mt-0.5 text-[#0a2540]">{{ $invoice->currency }}</dd>
                    </div>
                </dl>
            </div>

            <div class="mt-6 rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
                <h2 class="text-sm font-medium text-[#8792a2]">Activity</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    <li class="flex justify-between">
                        <span class="text-[#0a2540]">Created by {{ $invoice->createdBy->name }}</span>
                        <span class="text-[#8792a2]">{{ $invoice->created_at->format('d M Y, H:i') }}</span>
                    </li>
                    @if ($invoice->updated_at->notEqualTo($invoice->created_at))
                        <li class="flex justify-between">
                            <span class="text-[#0a2540]">Last updated</span>
                            <span class="text-[#8792a2]">{{ $invoice->updated_at->format('d M Y, H:i') }}</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</x-layout>
