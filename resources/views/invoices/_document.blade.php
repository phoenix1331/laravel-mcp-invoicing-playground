@php
    $statusColors = [
        'draft' => 'bg-slate-100 text-slate-700',
        'sent' => 'bg-indigo-50 text-indigo-700',
        'paid' => 'bg-emerald-50 text-emerald-700',
        'void' => 'bg-amber-50 text-amber-700',
    ];
@endphp

<div class="rounded-lg bg-white p-8 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[#0a2540]">Invoice {{ $invoice->number }}</h1>
            <span class="mt-2 inline-block rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$invoice->status->value] }}">
                {{ ucfirst($invoice->status->value) }}
            </span>
        </div>

        <div class="text-right text-sm text-[#425466]">
            <p class="font-medium text-[#0a2540]">{{ $invoice->organisation->name }}</p>
            @if ($invoice->organisation->address)
                <p>{{ $invoice->organisation->address }}</p>
            @endif
            @if ($invoice->organisation->vat_number)
                <p>VAT {{ $invoice->organisation->vat_number }}</p>
            @endif
        </div>
    </div>

    <div class="mt-8 grid grid-cols-2 gap-8 text-sm">
        <div>
            <p class="text-[#8792a2]">Billed to</p>
            <p class="mt-1 font-medium text-[#0a2540]">{{ $invoice->customer->name }}</p>
            @if ($invoice->customer->address)
                <p class="text-[#425466]">{{ $invoice->customer->address }}</p>
            @endif
            @if ($invoice->customer->email)
                <p class="text-[#425466]">{{ $invoice->customer->email }}</p>
            @endif
        </div>

        <div class="text-right">
            <dl class="space-y-1">
                <div class="flex justify-end gap-4">
                    <dt class="text-[#8792a2]">Issue date</dt>
                    <dd class="text-[#0a2540]">{{ $invoice->issue_date->format('d M Y') }}</dd>
                </div>
                <div class="flex justify-end gap-4">
                    <dt class="text-[#8792a2]">Due date</dt>
                    <dd class="text-[#0a2540]">{{ $invoice->due_date->format('d M Y') }}</dd>
                </div>
                <div class="flex justify-end gap-4">
                    <dt class="text-[#8792a2]">Currency</dt>
                    <dd class="text-[#0a2540]">{{ $invoice->currency }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <table class="mt-8 min-w-full divide-y divide-[#e3e8ee]">
        <thead>
            <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                <th class="py-3">Description</th>
                <th class="py-3 text-right">Qty</th>
                <th class="py-3 text-right">Unit price</th>
                <th class="py-3 text-right">Line total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#e3e8ee]">
            @foreach ($invoice->lines as $line)
                <tr>
                    <td class="py-3 text-sm text-[#0a2540]">{{ $line->description }}</td>
                    <td class="py-3 text-right text-sm tabular-nums text-[#425466]">{{ $line->quantity }}</td>
                    <td class="py-3 text-right text-sm tabular-nums text-[#425466]">£{{ number_format($line->unit_price, 2) }}</td>
                    <td class="py-3 text-right text-sm tabular-nums text-[#0a2540]">£{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 flex justify-end">
        <dl class="w-64 space-y-1 text-sm">
            <div class="flex justify-between">
                <dt class="text-[#425466]">Subtotal</dt>
                <dd class="tabular-nums text-[#0a2540]">£{{ number_format($invoice->subtotal, 2) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-[#425466]">Tax ({{ $invoice->tax_rate }}%)</dt>
                <dd class="tabular-nums text-[#0a2540]">£{{ number_format($invoice->tax_total, 2) }}</dd>
            </div>
            <div class="flex justify-between border-t border-[#e3e8ee] pt-1 font-semibold">
                <dt class="text-[#0a2540]">Total</dt>
                <dd class="tabular-nums text-[#0a2540]">£{{ number_format($invoice->total, 2) }}</dd>
            </div>
        </dl>
    </div>

    @if ($invoice->notes)
        <div class="mt-8 border-t border-[#e3e8ee] pt-4">
            <p class="text-sm font-medium text-[#8792a2]">Notes</p>
            <p class="mt-1 text-sm text-[#0a2540]">{{ $invoice->notes }}</p>
        </div>
    @endif
</div>
