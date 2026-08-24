<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Support\UntrustedText;
use App\Models\Invoice;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('A single invoice, including its line items and customer, as readable markdown.')]
class InvoiceResource extends Resource implements HasUriTemplate
{
    use AuthorizesToolAccess;

    protected string $mimeType = 'text/markdown';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('invoice://{invoiceId}');
    }

    public function handle(Request $request): Response
    {
        $invoice = Invoice::query()->find($request->get('invoiceId'));

        if (! $invoice instanceof Invoice) {
            return Response::error("No invoice was found with id {$request->get('invoiceId')}.");
        }

        if ($error = $this->authorizeTool($request, 'view', $invoice)) {
            return $error;
        }

        $invoice->load(['lines', 'customer']);

        $customer = $invoice->customer()->firstOrFail();

        $lines = $invoice->lines->map(fn ($line): string => sprintf(
            '| %s | %s | %s | %s |',
            UntrustedText::wrap($line->description),
            $line->quantity,
            $line->unit_price,
            $line->line_total,
        ))->implode("\n");

        $customerName = UntrustedText::wrap($customer->name);

        return Response::text(<<<MARKDOWN
            # Invoice {$invoice->number}

            Status: {$invoice->status->value}
            Customer: {$customerName}
            Issue date: {$invoice->issue_date->toDateString()}
            Due date: {$invoice->due_date->toDateString()}

            | Description | Quantity | Unit price | Line total |
            |---|---|---|---|
            {$lines}

            Subtotal: {$invoice->currency} {$invoice->subtotal}
            Tax ({$invoice->tax_rate}%): {$invoice->currency} {$invoice->tax_total}
            Total: {$invoice->currency} {$invoice->total}
            MARKDOWN);
    }
}
