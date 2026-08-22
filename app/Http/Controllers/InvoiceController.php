<?php

namespace App\Http\Controllers;

use App\Actions\AllocateInvoiceNumber;
use App\Actions\CalculateLineTotal;
use App\Actions\RecalculateInvoiceTotals;
use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Invoice::class);

        $customers = Customer::query()->orderBy('name')->get();

        return view('invoices.create', ['customers' => $customers]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $invoice = DB::transaction(function () use ($request, $user) {
            $number = app(AllocateInvoiceNumber::class)($user->organisation()->firstOrFail());

            $invoice = Invoice::create([
                ...$request->safe()->except('lines'),
                'organisation_id' => $user->organisation_id,
                'created_by_user_id' => $user->id,
                'number' => $number,
                'status' => InvoiceStatus::Draft,
            ]);

            $this->syncLines($invoice, $request->validated('lines'));

            return $invoice;
        });

        app(RecalculateInvoiceTotals::class)($invoice);

        return redirect()->route('invoices.edit', $invoice);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);

        $invoice->load('lines');
        $customers = Customer::query()->orderBy('name')->get();

        return view('invoices.edit', ['invoice' => $invoice, 'customers' => $customers]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        DB::transaction(function () use ($request, $invoice) {
            $invoice->update($request->safe()->except('lines'));

            $invoice->lines()->delete();
            $this->syncLines($invoice, $request->validated('lines'));
        });

        app(RecalculateInvoiceTotals::class)($invoice);

        return redirect()->route('invoices.edit', $invoice);
    }

    /**
     * @param  array<int, array{description: string, quantity: float, unit_price: float}>  $lines
     */
    private function syncLines(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $position => $line) {
            $invoice->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => app(CalculateLineTotal::class)((float) $line['quantity'], (float) $line['unit_price']),
                'position' => $position,
            ]);
        }
    }
}
