<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetInvoice extends Tool
{
    use AuthorizesToolAccess;

    protected string $description = 'Get a single invoice, including its line items and customer, by id.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()
                ->required()
                ->description('The id of the invoice to retrieve.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'number' => $schema->string()->required(),
            'status' => $schema->string()->enum(InvoiceStatus::class)->required(),
            'issue_date' => $schema->string()->format('date')->required(),
            'due_date' => $schema->string()->format('date')->required(),
            'currency' => $schema->string()->required(),
            'notes' => $schema->string()->nullable(),
            'subtotal' => $schema->number()->required(),
            'tax_rate' => $schema->number()->required(),
            'tax_total' => $schema->number()->required(),
            'total' => $schema->number()->required(),
            'customer' => $schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
                'email' => $schema->string()->nullable(),
            ])->required(),
            'lines' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->required(),
                    'description' => $schema->string()->required(),
                    'quantity' => $schema->number()->required(),
                    'unit_price' => $schema->number()->required(),
                    'line_total' => $schema->number()->required(),
                ]))
                ->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'invoice_id' => ['required', 'integer'],
        ]);

        $invoice = Invoice::query()->find($request->get('invoice_id'));

        if (! $invoice instanceof Invoice) {
            return Response::error("No invoice was found with id {$request->get('invoice_id')}.");
        }

        if ($error = $this->authorizeTool($request, 'view', $invoice)) {
            return $error;
        }

        $invoice->load(['lines', 'customer']);

        $customer = $invoice->customer()->firstOrFail();

        $data = [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'currency' => $invoice->currency,
            'notes' => $invoice->notes,
            'subtotal' => (float) $invoice->subtotal,
            'tax_rate' => (float) $invoice->tax_rate,
            'tax_total' => (float) $invoice->tax_total,
            'total' => (float) $invoice->total,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
            ])->all(),
        ];

        $summary = Response::text("Invoice {$invoice->number} ({$invoice->status->value}) for {$customer->name}: {$invoice->currency} {$invoice->total}.");

        return Response::make($summary)->withStructuredContent($data);
    }
}
