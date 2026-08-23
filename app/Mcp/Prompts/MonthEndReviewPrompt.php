<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Walks the model through a period summary of invoicing activity for a given month.')]
class MonthEndReviewPrompt extends Prompt
{
    protected string $name = 'month-end-review';

    public function arguments(): array
    {
        return [
            new Argument('month', 'The month to review, e.g. "2026-07" or "July 2026".', required: true),
        ];
    }

    public function handle(Request $request): Response
    {
        $month = $request->get('month');

        return Response::text(<<<TEXT
            Produce a month-end review for {$month}.

            1. Call reports.summary for the headline figures: outstanding, overdue,
               paid this month, drafts, and the revenue_by_month series. Note that
               paid_this_month always refers to the current calendar month on the
               server, not necessarily {$month} - if {$month} is not the current month,
               rely on revenue_by_month and invoices.list instead for that period's
               actual figures.
            2. Call invoices.list filtered by from and to covering {$month}, sorted by
               issue_date, to get the invoices actually issued in that period. Note how
               many were created, sent, paid, and voided.
            3. Call reports.aging for the current state of receivables, so the review
               can note what is still outstanding as of today even though it was issued
               in {$month}.
            4. Summarise: total invoiced in the period, total collected, what remains
               outstanding and how overdue it is, any voided invoices and why (if
               visible from their notes), and a comparison to the prior month if
               revenue_by_month covers it.
            5. Flag anything that looks like it needs attention: a customer with several
               overdue invoices, an unusually high draft count, or a period with no
               invoices sent at all.

            Present this as a short written summary, not a raw dump of the tool
            responses - the point is the narrative, with the numbers to back it up.
            TEXT);
    }
}
