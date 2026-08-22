<?php

namespace App\Http\Controllers;

use App\Actions\AllocateInvoiceNumber;
use App\Actions\CalculateLineTotal;
use App\Actions\DeleteInvoice;
use App\Actions\RecalculateInvoiceTotals;
use App\Actions\TransitionInvoiceStatus;
use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->filteredInvoices($request);
        $customers = Customer::query()->orderBy('name')->get();

        return view('invoices.index', ['invoices' => $invoices, 'customers' => $customers]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['lines', 'customer', 'createdBy']);

        return view('invoices.show', ['invoice' => $invoice]);
    }

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
     * Transition the invoice from draft to sent.
     */
    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        return $this->applyTransition($invoice, fn () => app(TransitionInvoiceStatus::class)->send($invoice));
    }

    /**
     * Transition the invoice from sent to paid.
     */
    public function markPaid(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        return $this->applyTransition($invoice, fn () => app(TransitionInvoiceStatus::class)->markPaid($invoice));
    }

    /**
     * Transition the invoice from sent to void.
     */
    public function void(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        return $this->applyTransition($invoice, fn () => app(TransitionInvoiceStatus::class)->void($invoice));
    }

    /**
     * Remove the specified resource from storage.
     *
     * Only draft invoices are actually deleted; anything else is voided
     * instead, per brief §4/§7 (see App\Actions\DeleteInvoice).
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        app(DeleteInvoice::class)($invoice);

        return redirect()->route('invoices.index');
    }

    private function applyTransition(Invoice $invoice, callable $transition): RedirectResponse
    {
        try {
            $transition();
        } catch (DomainException $exception) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice);
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

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    private function filteredInvoices(Request $request): LengthAwarePaginator
    {
        $sortableColumns = ['number', 'issue_date', 'due_date', 'total', 'status'];
        $sort = in_array($request->string('sort')->value(), $sortableColumns, true)
            ? $request->string('sort')->value()
            : 'issue_date';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';

        return Invoice::query()
            ->with('customer')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('issue_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('issue_date', '<=', $request->date('to')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->value();

                $query->where(function ($query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();
    }
}
