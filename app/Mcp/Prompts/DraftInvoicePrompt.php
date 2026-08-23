<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Guides the model through creating a well-formed draft invoice for a customer.')]
class DraftInvoicePrompt extends Prompt
{
    protected string $name = 'draft-invoice';

    public function arguments(): array
    {
        return [
            new Argument('customer', 'The customer name or id to invoice.', required: true),
            new Argument('items', 'A free-text description of the line items, e.g. "2 hours consulting at £150/hr, 1 laptop stand at £45".', required: true),
            new Argument('tone', 'The tone for any notes field: formal, friendly, or plain. Defaults to plain.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $customer = $request->get('customer');
        $items = $request->get('items');
        $tone = $request->get('tone') ?: 'plain';

        return Response::text(<<<TEXT
            Draft an invoice for "{$customer}" with these line items: "{$items}".

            1. Resolve the customer: call customers.list (or customers.get if you already
               have an id) to find the customer matching "{$customer}". If none matches,
               ask before calling customers.create - do not invent a customer.
            2. Turn the line items into the lines array invoices.create expects
               (description, quantity, unit_price for each). Ask for clarification on
               anything ambiguous (missing price, unclear quantity) rather than guessing.
            3. Call invoices.create with the resolved customer_id, today's date as
               issue_date, a reasonable due_date (30 days out unless told otherwise), the
               organisation's currency and tax_rate (check organisation.get if unsure),
               and the lines. Pass an idempotency_key so a retry cannot create a duplicate
               invoice.
            4. The invoice is created as a draft - it is not sent. Confirm with the user
               before calling invoices.send, since that is a separate, deliberate step.
            5. Write any notes field in a {$tone} tone.

            Remember: totals are always calculated server-side from the lines, never
            trust or set a total yourself. If the customer name is ambiguous (matches
            more than one customer), list the candidates and ask which one instead of
            picking for them.
            TEXT);
    }
}
