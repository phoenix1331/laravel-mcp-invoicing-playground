@php
    $invoice ??= null;
    $initialLines = $invoice
        ? $invoice->lines->map(fn ($line) => ['description' => $line->description, 'quantity' => (float) $line->quantity, 'unit_price' => (float) $line->unit_price])->values()->all()
        : [['description' => '', 'quantity' => 1, 'unit_price' => 0]];
@endphp

<div
    x-data="{
        lines: {{ json_encode($initialLines) }},
        taxRate: {{ old('tax_rate', $invoice->tax_rate ?? 20) }},
        addLine() {
            this.lines.push({ description: '', quantity: 1, unit_price: 0 });
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        },
        lineTotal(line) {
            return (Number(line.quantity) || 0) * (Number(line.unit_price) || 0);
        },
        get subtotal() {
            return this.lines.reduce((sum, line) => sum + this.lineTotal(line), 0);
        },
        get taxTotal() {
            return this.subtotal * ((Number(this.taxRate) || 0) / 100);
        },
        get total() {
            return this.subtotal + this.taxTotal;
        },
    }"
>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="customer_id" class="mb-1.5 block text-sm font-medium text-[#0a2540]">Customer</label>
            <select
                id="customer_id"
                name="customer_id"
                required
                class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] shadow-sm focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
            >
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('customer_id', $invoice->customer_id ?? null) == $customer->id)>
                        {{ $customer->name }}
                    </option>
                @endforeach
            </select>
            @error('customer_id')
                <p class="mt-1.5 text-sm text-[#df1b41]">{{ $message }}</p>
            @enderror
        </div>

        <x-form.input label="Currency" name="currency" value="{{ old('currency', $invoice->currency ?? 'GBP') }}" required />
        <x-form.input label="Issue date" name="issue_date" type="date" value="{{ old('issue_date', optional($invoice?->issue_date)->format('Y-m-d')) }}" required />
        <x-form.input label="Due date" name="due_date" type="date" value="{{ old('due_date', optional($invoice?->due_date)->format('Y-m-d')) }}" required />

        <div>
            <label for="tax_rate" class="mb-1.5 block text-sm font-medium text-[#0a2540]">Tax rate (%)</label>
            <input
                id="tax_rate"
                name="tax_rate"
                type="number"
                step="0.01"
                x-model="taxRate"
                class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] shadow-sm focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
            >
            @error('tax_rate')
                <p class="mt-1.5 text-sm text-[#df1b41]">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="mt-4">
        <label for="notes" class="mb-1.5 block text-sm font-medium text-[#0a2540]">Notes</label>
        <textarea
            id="notes"
            name="notes"
            rows="3"
            class="block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] shadow-sm focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
        >{{ old('notes', $invoice->notes ?? '') }}</textarea>
    </div>

    <h2 class="mt-8 text-lg font-semibold text-[#0a2540]">Line items</h2>

    <div class="mt-4 overflow-hidden rounded-lg bg-white shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-[#e3e8ee]">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-[#8792a2]">
                    <th class="px-4 py-3">Description</th>
                    <th class="w-24 px-4 py-3">Qty</th>
                    <th class="w-32 px-4 py-3">Unit price</th>
                    <th class="w-32 px-4 py-3 text-right">Line total</th>
                    <th class="w-12 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e8ee]">
                <template x-for="(line, index) in lines" :key="index">
                    <tr>
                        <td class="px-4 py-3">
                            <input
                                type="text"
                                :name="`lines[${index}][description]`"
                                x-model="line.description"
                                required
                                class="block w-full rounded-md border border-[#e3e8ee] px-2 py-1.5 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
                            >
                        </td>
                        <td class="px-4 py-3">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                :name="`lines[${index}][quantity]`"
                                x-model="line.quantity"
                                required
                                class="block w-full rounded-md border border-[#e3e8ee] px-2 py-1.5 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
                            >
                        </td>
                        <td class="px-4 py-3">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                :name="`lines[${index}][unit_price]`"
                                x-model="line.unit_price"
                                required
                                class="block w-full rounded-md border border-[#e3e8ee] px-2 py-1.5 text-sm text-[#0a2540] focus:border-[#f87171] focus:outline-none focus:ring-1 focus:ring-[#f87171]"
                            >
                        </td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums text-[#0a2540]" x-text="lineTotal(line).toFixed(2)"></td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" @click="removeLine(index)" class="text-[#8792a2] hover:text-[#df1b41]" aria-label="Remove line">
                                &times;
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div class="border-t border-[#e3e8ee] px-4 py-3">
            <button type="button" @click="addLine()" class="text-sm font-medium text-[#f87171] hover:text-[#ef4444]">
                + Add line
            </button>
        </div>

        <div class="border-t border-[#e3e8ee] bg-[#f6f9fc] px-4 py-3">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-[#425466]">Subtotal</dt>
                    <dd class="tabular-nums text-[#0a2540]" x-text="subtotal.toFixed(2)"></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-[#425466]">Tax</dt>
                    <dd class="tabular-nums text-[#0a2540]" x-text="taxTotal.toFixed(2)"></dd>
                </div>
                <div class="flex justify-between font-semibold">
                    <dt class="text-[#0a2540]">Total</dt>
                    <dd class="tabular-nums text-[#0a2540]" x-text="total.toFixed(2)"></dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-6 max-w-xs">
        <x-form.button type="submit">
            {{ $invoice ? 'Save changes' : 'Create invoice' }}
        </x-form.button>
    </div>
</div>
