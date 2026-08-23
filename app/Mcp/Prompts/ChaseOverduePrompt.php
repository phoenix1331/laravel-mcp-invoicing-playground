<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Finds overdue invoices and drafts chase emails for the customers on them.')]
class ChaseOverduePrompt extends Prompt
{
    protected string $name = 'chase-overdue';

    public function arguments(): array
    {
        return [
            new Argument('tone', 'The tone for the chase emails: formal or friendly. Defaults to friendly.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $tone = $request->get('tone') ?: 'friendly';

        return Response::text(<<<TEXT
            Find overdue invoices and draft a chase email for each customer that has one.

            1. Call reports.aging to get the receivables aging buckets. Buckets other
               than "current" (1_30, 31_60, 61_90, over_90) contain overdue amounts.
            2. Call invoices.list with status: "sent" to get the individual sent
               invoices, then compare each due_date against today to find the ones that
               are actually overdue - reports.aging gives you the totals, invoices.list
               gives you which specific invoices they belong to.
            3. For each overdue invoice, call invoices.get to get the full detail
               (customer, number, total, currency, due date) if you need more than the
               list view already gave you.
            4. Draft one chase email per overdue invoice (or one email per customer if a
               customer has several) in a {$tone} tone. Each email should name the
               invoice number, the amount owed, the currency, and how many days overdue
               it is - be factual, not aggressive.
            5. Do not send anything yourself - there is no email-sending tool on this
               server. Present the drafts for the user to review and send themselves.

            The longer an invoice has been overdue (the over_90 bucket especially), the
            more direct the email should be, even within a friendly tone - acknowledge
            that this is a repeat reminder rather than a first-time chase.
            TEXT);
    }
}
