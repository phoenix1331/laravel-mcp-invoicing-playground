<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Invoice;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListInvoices extends Tool
{
    use AuthorizesToolAccess;

    protected string $description = 'List the caller\'s organisation invoices, optionally filtered by status, customer or issue date range, and paginated.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(InvoiceStatus::class)
                ->description('Only return invoices with this status.'),
            'customer_id' => $schema->integer()
                ->description('Only return invoices for this customer.'),
            'from' => $schema->string()
                ->format('date')
                ->description('Only return invoices issued on or after this date (YYYY-MM-DD).'),
            'to' => $schema->string()
                ->format('date')
                ->description('Only return invoices issued on or before this date (YYYY-MM-DD).'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('The page of results to return.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'invoices' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->required(),
                    'number' => $schema->string()->required(),
                    'status' => $schema->string()->enum(InvoiceStatus::class)->required(),
                    'customer_id' => $schema->integer()->required(),
                    'customer_name' => $schema->string()->required(),
                    'issue_date' => $schema->string()->format('date')->required(),
                    'due_date' => $schema->string()->format('date')->required(),
                    'total' => $schema->number()->required(),
                    'currency' => $schema->string()->required(),
                ]))
                ->required(),
            'current_page' => $schema->integer()->required(),
            'last_page' => $schema->integer()->required(),
            'total' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Generator
    {
        if ($error = $this->authorizeTool($request, 'viewAny', Invoice::class)) {
            yield $error;

            return;
        }

        $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(InvoiceStatus::cases(), 'value'))],
            'customer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        yield Response::notification('notifications/progress', [
            'progress' => 0,
            'total' => 100,
            'message' => 'Querying invoices...',
        ]);

        $paginator = Invoice::query()
            ->with('customer')
            ->when($request->get('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->get('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($request->get('from'), fn ($query, $from) => $query->whereDate('issue_date', '>=', $from))
            ->when($request->get('to'), fn ($query, $to) => $query->whereDate('issue_date', '<=', $to))
            ->orderByDesc('issue_date')
            ->paginate(20, page: (int) $request->get('page', 1));

        yield Response::notification('notifications/progress', [
            'progress' => 100,
            'total' => 100,
            'message' => "Found {$paginator->total()} invoices.",
        ]);

        $invoices = $paginator->getCollection()->map(function (Invoice $invoice): array {
            $customer = $invoice->customer()->firstOrFail();

            return [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $customer->name,
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'total' => (float) $invoice->total,
                'currency' => $invoice->currency,
            ];
        })->all();

        $summary = Response::text("Found {$paginator->total()} invoice(s), page {$paginator->currentPage()} of {$paginator->lastPage()}.");

        yield Response::make($summary)->withStructuredContent([
            'invoices' => $invoices,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }
}
